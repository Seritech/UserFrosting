<?php

namespace UserFrosting;

/**
 * TokenController Class
 *
 * Controller class for /tokens/* URLs.  Handles token-related activities,
 * including listing tokens, CRUD for tokens, etc.
 *
 * @package UserFrosting
 * @author Johan Cwiklinski
 * @link http://www.userfrosting.com/navigating/#structure
 */
class TokenController extends \UserFrosting\BaseController
{

    /**
     * Create a new TokenController object.
     *
     * @param UserFrosting $app The main UserFrosting app.
     */
    public function __construct($app)
    {
        $this->_app = $app;
    }

    /**
     * Renders the tokens listing page.
     *
     * This page renders a table of tokens, with dropdown menus for admin actions for each token.
     * Actions typically include: edit token, activate token, enable/disable token, delete token.
     * This page requires authentication.
     * Request type: GET
     * @param bool $paginate_server_side optional.  Set to true if you want UF to load each page
     * of results via AJAX on demand, rather than all at once.
     *
     * @return void
     */
    public function pageTokens($paginate_server_side = true)
    {
        // Access-controlled page
        if (!$this->_app->user->checkAccess('uri_tokens')) {
            $this->_app->halt(403);
        }

        if (!$paginate_server_side) {
            $token_collection = Token::get();
            $token_collection->getRecentEvents('sign_in');
            $token_collection->getRecentEvents('sign_up', 'sign_up_time');
        }
        $name = "Tokens";
        $icon = "fa fa-user-secret";

        $this->_app->render('tokens/tokens.twig', [
            "box_title"             => $name,
            "icon"                  => $icon,
            "paginate_server_side"  => $paginate_server_side,
            "tokens"                => isset($token_collection) ? $token_collection->toArray() : []
        ]);
    }

    /**
     * Renders the form for creating a new token.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the form,
     * which can be embedded in other pages.
     * The form can be rendered in "modal" (for popup) or "panel" mode, depending on the
     * value of the GET parameter `render`.
     * This page requires authentication.
     * Request type: GET
     *
     * @return void
     */
    public function formTokenCreate()
    {
        // Access-controlled resource
        if (!$this->_app->user->checkAccess('create_token')) {
            $this->_app->halt(403);
        }

        $get = $this->_app->request->get();

        if (isset($get['render'])) {
            $render = $get['render'];
        } else {
            $render = "modal";
        }

        // Create a dummy token to prepopulate fields
        $target_token = new Token();

        if ($render == "modal") {
            $template = "components/common/token-info-modal.twig";
        } else {
            $template = "components/common/token-info-panel.twig";
        }

        // Determine authorized fields for those that have default values.  Don't hide any fields
        $fields = ['title', 'locale'];
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field) {
            if ($this->_app->user->checkAccess("update_account_setting", ["token" => $target_token, "property" => $field])) {
                $show_fields[] = $field;
            } else {
                $disabled_fields[] = $field;
            }
        }

        // Load validator rules
        $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/token-create.json");
        $this->_app->jsValidator->setSchema($schema);

        $this->_app->render($template, [
            "box_id"        => $get['box_id'],
            "box_title"     => "Create Token",
            "submit_button" => "Create token",
            "form_action"   => $this->_app->site->uri['public'] . "/tokens",
            "target_token"   => $target_token,
            "fields"        => [
                "disabled"  => $disabled_fields,
                "hidden"    => $hidden_fields
            ],
            "buttons"       => [
                "hidden"    => [
                    "edit", "enable", "delete", "activate"
                ]
            ],
            "validators"    => $this->_app->jsValidator->rules()
        ]);
    }

    /**
     * Renders the form for editing an existing token.
     *
     * This does NOT render a complete page.  Instead, it renders the HTML for the form,
     * which can be embedded in other pages.
     * The form can be rendered in "modal" (for popup) or "panel" mode, depending on the
     * value of the GET parameter `render`.
     * For each field, we will first check if the currently logged-in user has permission to
     * update the field.  If so, the field will be rendered as editable.
     * If not, we will check if they have permission to view the field.  If so,
     * it will be displayed but disabled.  If they have neither permission, the field will be hidden.
     * This page requires authentication.
     * Request type: GET
     *
     * @param int $token_id the id of the token to edit.
     *
     * @return void
     */
    public function formTokenEdit($token_id)
    {
        // Get the token to edit
        $target_token = Token::find($token_id);

        // Access-controlled resource
        if (!$this->_app->user->checkAccess('uri_tokens')) {
            $this->_app->halt(403);
        }

        $get = $this->_app->request->get();

        if (isset($get['render'])) {
            $render = $get['render'];
        } else {
            $render = "modal";
        }

        if ($render == "modal") {
            $template = "components/common/token-info-modal.twig";
        } else {
            $template = "components/common/token-info-panel.twig";
        }

        // Determine authorized fields
        $fields = ['display_name'];
        $show_fields = [];
        $disabled_fields = [];
        $hidden_fields = [];
        foreach ($fields as $field) {
            if ($this->_app->user->checkAccess("update_account_setting", ["token" => $target_token, "property" => $field])) {
                $show_fields[] = $field;
            } else {
                $disabled_fields[] = $field;
            }
        }

        // Always disallow editing app name
        $disabled_fields[] = "app_name";

        // Load validator rules
        $schema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/token-update.json");
        $this->_app->jsValidator->setSchema($schema);

        $this->_app->render($template, [
            "box_id"        => $get['box_id'],
            "box_title"     => "Edit token",
            "submit_button" => "Update token",
            "form_action"   => $this->_app->site->uri['public'] . "/tokens/t/$token_id",
            "target_token"  => $target_token,
            "fields"        => [
                "disabled"  => $disabled_fields,
                "hidden"    => $hidden_fields
            ],
            "buttons"       => [
                "hidden"    => [
                    "edit", "enable", "delete", "activate"
                ]
            ],
            "validators" => $this->_app->jsValidator->rules()
        ]);
    }

    /**
     * Processes the request to create a new token (from the admin controls).
     *
     * Processes the request from the token creation form, checking that:
     * 1. The application name is not already in use;
     * 2. The logged-in user has the necessary permissions to update the posted field(s);
     * 3. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     * @see formTokenCreate
     *
     * @return void
     */
    public function createToken()
    {
        $post = $this->_app->request->post();

        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/token-create.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Access-controlled resource
        if (!$this->_app->user->checkAccess('create_token')) {
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);

        // Sanitize data
        $rf->sanitize();

        // Validate, and halt on validation errors.
        $error = !$rf->validate(true);

        // Get the filtered data
        $data = $rf->data();

        // Remove csrf_token from object data
        $rf->removeFields(['csrf_token']);

        // Perform desired data transformations on required fields.  Is this a feature we could add to Fortress?
        $data['display_name'] = trim($data['display_name']);
        $data['token'] = Token::generateToken();

        // Check if application name already exists
        if (Token::where('app_name', $data['app_name'])->first()) {
            $ms->addMessageTranslated("danger", "TOKEN_APPNAME_IN_USE", $data);
            $error = true;
        }

        // Halt on any validation errors
        if ($error) {
            $this->_app->halt(400);
        }

        // Create the token
        $token = new Token($data);

        // Create events - account creation and password reset
        $token->newEventSignUp($this->_app->user);

        // Save token again after creating events
        $token->save();

        $twig = $this->_app->view()->getEnvironment();

        // Success message even if we can't email them
        $ms->addMessageTranslated("success", "TOKEN_CREATION_COMPLETE", $data);
    }

    /**
     * Processes the request to update an existing tokens's details, including enabled/disabled.
     *
     * Processes the request from the token update form, checking that:
     * 1. The logged-in user has the necessary permissions to update the posted field(s);
     * 2. The submitted data is valid.
     * This route requires authentication.
     * Request type: POST
     *
     * @param int $token_id the id of the token to edit.
     * @see formTokenEdit
     *
     * @return void
     */
    public function updateToken($token_id)
    {
        $post = $this->_app->request->post();

        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/token-update.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Get the target token
        $target_token = Token::find($token_id);

        // Access-controlled resource
        if (!$this->_app->user->checkAccess('update_token')) {
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        // Remove csrf_token
        unset($post['csrf_token']);

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $post);

        // Check authorization for submitted fields, if the value has been changed
        foreach ($post as $name => $value) {
            if (!isset($target_token->$name)) {
                $ms->addMessageTranslated("danger", "NO_DATA");
                $this->_app->halt(400);
            }
        }

        // Sanitize
        $rf->sanitize();

        // Validate, and halt on validation errors.
        if (!$rf->validate()) {
            $this->_app->halt(400);
        }

        // Get the filtered data
        $data = $rf->data();

        // Update the token and generate success messages
        foreach ($data as $name => $value) {
            if ($value != $target_token->$name) {
                $target_token->$name = $value;
            }
        }

        $ms->addMessageTranslated("success", "TOKEN_DETAILS_UPDATED", ["app_name" => $target_token->app_name]);
        $target_token->save();
    }

    /**
     * Processes the request to reset an existing token.
     *
     * Before doing so, checks that:
     * 1. You have permission to update tokens.
     * This route requires authentication (and should generally be limited to admins or the root user).
     * Request type: POST
     *
     * @param int $token_id the id of the token to delete.
     *
     * @return voic
     */
    public function resetToken($token_id)
    {
        $post = $this->_app->request->post();

        // Get the target token
        $target_token = Token::find($token_id);

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Check authorization
        if (!$this->_app->user->checkAccess('update_token', ['token' => $target_token])) {
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        $ms->addMessageTranslated("success", "TOKEN_RESET_SUCCESSFUL", ["app_name" => $target_token->app_name]);
        $target_token->token = Token::generateToken();
        $target_token->save();
    }

    /**
     * Processes the request to delete an existing token.
     *
     * Deletes the specified token, removing associations.
     * Before doing so, checks that:
     * 1. You have permission to delete tokens.
     * This route requires authentication (and should generally be limited to admins or the root user).
     * Request type: POST
     *
     * @param int $token_id the id of the token to delete.
     *
     * @return voic
     */
    public function deleteToken($token_id)
    {
        $post = $this->_app->request->post();

        // Get the target token
        $target_token = Token::find($token_id);

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Check authorization
        if (!$this->_app->user->checkAccess('delete_token', ['token' => $target_token])) {
            $ms->addMessageTranslated("danger", "ACCESS_DENIED");
            $this->_app->halt(403);
        }

        $ms->addMessageTranslated("success", "TOKEN_DELETION_SUCCESSFUL", ["app_name" => $target_token->app_name]);
        $target_token->delete();
        unset($target_token);
    }
}
