<?php

namespace UserFrosting;

use \Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Token Class
 *
 * Represents a Token object as stored in the database.
 *
 * @package UserFrosting
 * @author Johan Cwiklinski
 * @see http://www.userfrosting.com/tutorials/lesson-3-data-model/
 *
 * @property int id
 * @property string app_name
 * @property string display_name
 * @property string token
 * @property int flag_enabled
 * @property timestamp created_at
 * @property timestamp updated_at
 */
class Token extends UFModel
{

    /**
     * @var string The id of the table for the current model.
     */
    protected static $_table_id = "token";

    /**
     * @var TokenEvent[] An array of events to be inserted for this Token when save is called.
     */
    protected $new_events = [];

    /**
     * @var bool Enable timestamps for tokens.
     */
    public $timestamps = true;

    /**
     * Create a new Token object.
     *
     * @param array $properties ?
     */
    public function __construct($properties = [])
    {
        parent::__construct($properties);
    }

    /**
     * Determine if the property for this object exists.
     *
     * Every property in __get must also be implemented here for Twig to recognize it.
     * @param string $name the name of the property to check.
     *
     * @return bool true if the property is defined, false otherwise.
     */
    public function __isset($name)
    {
        if (in_array($name, [
                "last_sign_in_event",
                "last_sign_in_time",
                "sign_up_time",
                "last_reset_time",
            ])) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * Get a property for this object.
     *
     * @param string $name the name of the property to retrieve.
     * @throws Exception the property does not exist for this object.
     * @return string the associated property.
     */
    public function __get($name)
    {
        if ($name == "last_sign_in_event") {
            return $this->lastEvent('sign_in');
        } elseif ($name == "last_sign_in_time") {
            return $this->lastEventTime('sign_in');
        } elseif ($name == "sign_up_time") {
            return $this->lastEventTime('sign_up');
        } elseif ($name == "last_reset_time") {
            return $this->lastEventTime('reset_request');
        } else {
            return parent::__get($name);
        }
    }

    /**
     * Extends Eloquent's Collection models.
     *
     * @param array $models ?
     *
     * @return TokenCollection
     */
    public function newCollection(array $models = [])
    {
        return new TokenCollection($models);
    }

    /**
     * Get all events for this token.
     * @todo save events in $new_events as well?
     *
     * @return ?
     */
    public function events()
    {
        return $this->hasMany('UserFrosting\TokenEvent');
    }

    /**
     * Get the most recent time for a specified event type for this token.
     *
     * @param string $type Event type
     *
     * @return string|null The last event time, as a SQL formatted time
     * (YYYY-MM-DD HH:MM:SS), or null if an event of this type doesn't exist.
     */
    public function lastEventTime($type)
    {
        $result = $this->events()
            ->where('event_type', $type)
            ->max('occurred_at');
        return $result ? $result : null;
    }

    /**
     * Get the most recent event of a specified type for this token.
     *
     * @param string $type Event type
     *
     * @return TokenEvent
     */
    public function lastEvent($type)
    {
        return $this->events()
            ->where('event_type', $type)
            ->orderBy('occurred_at', 'desc')
            ->first();
    }

    /**
     * Create an event saying that this token has been generated.
     *
     * @param User $creator optional The User who created this account.
     *                      If set, this will be recorded in the event description.
     *
     * @return TokenEvent
     */
    public function newEventSignUp($creator = null)
    {
        if ($creator) {
            $description = "Token for {$this->app_name} created by {$creator->user_name} on " .
                date("Y-m-d H:i:s") . ".";
        } else {
            $description = "Token for {$this->app_name} created on " . date("Y-m-d H:i:s") . ".";
        }
        $event = new TokenEvent([
            "event_type"  => "sign_up",
            "description" => $description
        ]);
        $this->new_events[] = $event;
        return $event;
    }

    /**
     * Create a new token sign-in event.
     *
     * @return TokenEvent
     */
    public function newEventSignIn()
    {
        $event = new TokenEvent([
            "event_type"  => "sign_in",
            "description" => "Token for {$this->app_name} used at " . date("Y-m-d H:i:s") . "."
        ]);
        $this->new_events[] = $event;
        return $event;
    }

    /**
     * Create a new token check failed event.
     *
     * @param string $app_name Specified application name
     *
     * @return TokenEvent
     */
    public function newEventCheckFailed($app_name)
    {
        $event = new TokenEvent([
            "event_type"  => "check_failed",
            "description" => "Token for {$app_name} check failed at " . date("Y-m-d H:i:s") . "."
        ]);
        $this->new_events[] = $event;
        return $event;
    }

    /**
     * Generate a new token reset event
     *
     * @return TokenEvent
     */
    public function newEventReset()
    {
        $this->secret_token = self::generateToken();
        $event = new TokenEvent([
            "event_type"  => "reset_request",
            "description" => "Token for {$this->app_name} has been reset on " . date("Y-m-d H:i:s") . "."
        ]);
        $this->new_events[] = $event;
        return $event;
    }

    /**
     * Store the Token to the database, along with any new events, updating as necessary.
     *
     * @param array $options ?
     *
     * @return ?
     */
    public function save(array $options = [])
    {
        // Update the token record itself
        $result = parent::save($options);

        // Save any new events for this token
        foreach ($this->new_events as $event) {
            $this->events()->save($event);
        }

        return $result;
    }

    /**
     * Delete this token from the database, along with any authorization rules
     *
     * @return bool true if the deletion was successful, false otherwise.
     */
    public function delete()
    {
        // Remove all token events
        $event_table = Database::getSchemaTable('token_event')->name;
        Capsule::table($event_table)->where("token_id", $this->id)->delete();

        // Delete the token
        $result = parent::delete();

        return $result;
    }

    /**
     * Check token validity. Fire sign-in event on success, check_failed on fail.
     *
     * @param string $app_name Application name
     * @param string $token    Provided token
     *
     * @return boolean
     */
    public function check($app_name, $token)
    {
        $check = false;

        try {
            $dbtoken = self::where('app_name', $app_name)
                ->where('token', $token)
                ->where('flag_enabled', 1)
                ->firstOrFail();
            $check = true;
            $this->newEventSignIn();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $check = false;
            $this->newEventCheckFailed($app_name);
        }

        //Save anyways, to get events updated
        $this->save();

        return $check;
    }

    /**
     * Generate a new token.
     *
     * @return string
     */
    public static function generateToken()
    {
        do {
            $gen = md5(uniqid(mt_rand(), false));
        } while (Token::where('token', $gen)->first());
        return $gen;
    }
}
