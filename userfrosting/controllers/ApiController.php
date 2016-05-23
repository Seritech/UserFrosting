<?php

namespace UserFrosting;

use \Illuminate\Database\Capsule\Manager as Capsule;

/**
 * ApiController Class
 *
 * Controller class for /api/* URLs.  Handles all api requests.
 *
 * @package UserFrosting
 * @author Alex Weissman
 * @author Johan Cwiklinski
 * @link http://www.userfrosting.com/navigating/#structure
 */
class ApiController extends \UserFrosting\BaseController {

    /**
     * Create a new ApiController object.
     *
     * @param UserFrosting $app The main UserFrosting app.
     */
    public function __construct($app){
        $this->_app = $app;
    }

    /**
     * Returns a list of Users
     *
     * Generates a list of users, optionally paginated, sorted and/or filtered.
     * This page requires authentication.
     * Request type: GET
     */
    public function listUsers(){
        $get = $this->_app->request->get();

        $size = isset($get['size']) ? $get['size'] : null;
        $page = isset($get['page']) ? $get['page'] : null;
        $sort_field = isset($get['sort_field']) ? $get['sort_field'] : "user_name";
        $sort_order = isset($get['sort_order']) ? $get['sort_order'] : "asc";
        $filters = isset($get['filters']) ? $get['filters'] : [];
        $format = isset($get['format']) ? $get['format'] : "json";
        $primary_group_name = isset($get['primary_group']) ? $get['primary_group'] : null;

        // Optional filtering by primary group
        if ($primary_group_name){
            $primary_group = Group::where('name', $primary_group_name)->first();

            if (!$primary_group)
                $this->_app->notFound();

            // Access-controlled page
            if (!$this->_app->user->checkAccess('uri_group_users', ['primary_group_id' => $primary_group->id])){
                $this->_app->notFound();
            }

            $userQuery = new User;
            $userQuery = $userQuery->where('primary_group_id', $primary_group->id);

        } else {
            // Access-controlled page
            if (!$this->_app->user->checkAccess('uri_users')){
                $this->_app->notFound();
            }

            $userQuery = new User;
        }

        // Count unpaginated total
        $total = $userQuery->count();

        // Exclude fields
        $userQuery = $userQuery
                ->exclude(['password', 'secret_token']);

        //Capsule::connection()->enableQueryLog();

        // Get unfiltered, unsorted, unpaginated collection
        $user_collection = $userQuery->get();

        // Load recent events for all users and merge into the collection.  This can't be done in one query,
        // at least not efficiently.  See http://laravel.io/forum/04-05-2014-eloquent-eager-loading-to-limit-for-each-post
        $last_sign_in_times = $user_collection->getRecentEvents('sign_in');
        $last_sign_up_times = $user_collection->getRecentEvents('sign_up', 'sign_up_time');

        // Apply filters
        foreach ($filters as $name => $value){
            // For date filters, search for weekday, month, or year
            if ($name == 'last_sign_in_time') {
                $user_collection = $user_collection->filterRecentEventTime('sign_in', $last_sign_in_times, $value);
            } else if ($name == 'sign_up_time') {
                $user_collection = $user_collection->filterRecentEventTime('sign_up', $last_sign_up_times, $value, "Unknown");
            } else {
                $user_collection = $user_collection->filterTextField($name, $value);
            }
        }

        // Count filtered results
        $total_filtered = count($user_collection);

        // Sort
        if ($sort_order == "desc")
            $user_collection = $user_collection->sortByDesc($sort_field, SORT_NATURAL|SORT_FLAG_CASE);
        else
            $user_collection = $user_collection->sortBy($sort_field, SORT_NATURAL|SORT_FLAG_CASE);

        // Paginate
        if ( ($page !== null) && ($size !== null) ){
            $offset = $size*$page;
            $user_collection = $user_collection->slice($offset, $size);
        }

        $result = [
            "count" => $total,
            "rows" => $user_collection->values()->toArray(),
            "count_filtered" => $total_filtered
        ];

        //$query = Capsule::getQueryLog();

        if ($format == "csv"){
            $settings = http_build_query($get);
            $date = date("Ymd");
            $this->_app->response->headers->set('Content-Disposition', "attachment;filename=$date-users-$settings.csv");
            $this->_app->response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $keys = $user_collection->keys()->toArray();
            echo implode(array_keys($result['rows'][0]), ",") . "\r\n";
            foreach ($result['rows'] as $row){
                echo implode($row, ",") . "\r\n";
            }
        } else {
            // Be careful how you consume this data - it has not been escaped and contains untrusted user-supplied content.
            // For example, if you plan to insert it into an HTML DOM, you must escape it on the client side (or use client-side templating).
            $this->_app->response->headers->set('Content-Type', 'application/json; charset=utf-8');
            echo json_encode($result, JSON_PRETTY_PRINT);
        }
    }

    /**
     * Returns a list of Tokens
     *
     * Generates a list of tokens, optionally paginated, sorted and/or filtered.
     * This page requires authentication.
     * Request type: GET
     *
     * @return void
     */
    public function listTokens()
    {
        $get = $this->_app->request->get();

        $size = isset($get['size']) ? $get['size'] : null;
        $page = isset($get['page']) ? $get['page'] : null;
        $sort_field = isset($get['sort_field']) ? $get['sort_field'] : "app_name";
        $sort_order = isset($get['sort_order']) ? $get['sort_order'] : "asc";
        $filters = isset($get['filters']) ? $get['filters'] : [];
        $format = isset($get['format']) ? $get['format'] : "json";

        // Access-controlled page
        if (!$this->_app->user->checkAccess('uri_tokens')) {
            $this->_app->halt(403);
        }

        $tokenQuery = new Token;

        // Count unpaginated total
        $total = $tokenQuery->count();

        // Get unfiltered, unsorted, unpaginated collection
        $token_collection = $tokenQuery->get();

        // Load recent events for all users and merge into the collection.  This can't be done in one query,
        // at least not efficiently.  See http://laravel.io/forum/04-05-2014-eloquent-eager-loading-to-limit-for-each-post
        $last_sign_in_times = $token_collection->getRecentEvents('sign_in');
        $last_sign_up_times = $token_collection->getRecentEvents('sign_up', 'sign_up_time');

        // Count filtered results
        $total_filtered = count($token_collection);

        // Sort
        if ($sort_order == "desc") {
            $token_collection = $token_collection->sortByDesc($sort_field, SORT_NATURAL|SORT_FLAG_CASE);
        } else {
            $token_collection = $token_collection->sortBy($sort_field, SORT_NATURAL|SORT_FLAG_CASE);
        }

        // Paginate
        if (($page !== null) && ($size !== null)) {
            $offset = $size*$page;
            $token_collection = $token_collection->slice($offset, $size);
        }

        $result = [
            "count" => $total,
            "rows"  => $token_collection->values()->toArray()
        ];

        //$query = Capsule::getQueryLog();

        $this->_app->response->headers->set('Content-Type', 'application/json; charset=utf-8');

        echo json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * Authenticate user
     *
     * Request type: POST
     *
     * @return void
     */
    public function authenticate()
    {
        // Load the request schema
        $requestSchema = new \Fortress\RequestSchema($this->_app->config('schema.path') . "/forms/apiauth.json");

        // Get the alert message stream
        $ms = $this->_app->alerts;

        // Set up Fortress to process the request
        $rf = new \Fortress\HTTPRequestFortress($ms, $requestSchema, $this->_app->request->post());

        // Sanitize data
        $rf->sanitize();

        // Validate, and halt on validation errors.
        if (!$rf->validate(true)) {
            $this->_app->halt(400);
        }

        // Get the filtered data
        $data = $rf->data();

        try {
            $token = new Token;
            $check = $token->check(
                $data['app_name'],
                $data['api_token']
            );

            if (!$check) {
                throw new \RuntimeException('Invalid API token');
            }

            $ufield = 'user_name';

            // Determine whether we are trying to log in with an email address or a username
            $isEmail = filter_var($data['user_name'], FILTER_VALIDATE_EMAIL);

            // If it's an email address, but email login is not enabled, raise an error.
            if ($isEmail && !$this->_app->site->email_login) {
                throw new \RuntimeException('Email login is disabled');
            } elseif ($isEmail) {
                $ufield = 'email';
            }

            //retrieve user
            $user = User::where($ufield, $data['user_name'])
                ->where('flag_verified', 1)
                ->where('flag_enabled', 1)
                ->firstOrFail();

            //check user password
            if (!$user->verifyPassword($data['password'])) {
                throw new \RuntimeException('Invalid password');
            }

            if ($user->id == $this->_app->config('user_id_master')) {
                throw new \RuntimeException('No API login with root account');
            }

            $sent_group = $data['group'];
            $groups = $user->groups->filter(function ($group) use ($sent_group) {
                return $group->name === $sent_group;
            });

            if ($groups->count() !== 1) {
                throw new \RuntimeException('Not member of group ' . $sent_group);
            }

            $this->authSuccess();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                //error message is not comprehensive for humans, rewrite it
                $message = 'User not found';
            }

            $this->authFailed($message);
        }
    }

    /**
     * Response on API authentication fail
     *
     * @param string $message Error message
     *
     * @return void
     */
    private function authFailed($message)
    {
        $response = $this->_app->response;
        $response->setStatus(403);
        $response->headers->set('Content-Type', 'application/json');

        $json = [
            'auth'  => 'false',
            'err'   => $message
        ];
        $response->write(json_encode($json, JSON_PRETTY_PRINT));
    }

    /**
     * Response on API authentication success
     *
     * @return void
     */
    private function authSuccess()
    {
        $response = $this->_app->response;
        $response->setStatus(200);
        $response->headers->set('Content-Type', 'application/json');
        $response->write(json_encode(['auth' => 'true'], JSON_PRETTY_PRINT));
    }
}
