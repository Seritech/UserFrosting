<?php

namespace UserFrosting;

use \Illuminate\Database\Capsule\Manager as Capsule;

/**
 * TokenEvent Class
 *
 * Represents a single token event at a specified point in time.
 *
 * @package UserFrosting
 * @author Johan Cwiklinski
 */
class TokenEvent extends UFModel
{
    /**
     * @var string The id of the table for the current model.
     */
    protected static $_table_id = "token_event";

    /**
     * Add clauses to select the most recent event of each type for each user, to the query.
     *
     * @param ? $query ?
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeMostRecentEvents($query)
    {
        return $query->select('token_id', 'event_type', Capsule::raw('MAX(occurred_at) as occurred_at'))
            ->groupBy('token_id')
            ->groupBy('event_type');
    }

    /**
     * Add clauses to select the most recent event of a given type for each user, to the query.
     *
     * @param ?      $query ?
     * @param string $type  The type of event, matching the `event_type` field in the user_event table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeMostRecentEventsByType($query, $type)
    {
        return $query->select('token_id', Capsule::raw('MAX(occurred_at) as occurred_at'))
            ->where('event_type', $type)
            ->groupBy('token_id');
    }
}
