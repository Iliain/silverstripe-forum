<?php

namespace SilverStripe\Forum\Search;

use SilverStripe\Forum\Search\ForumDatabaseSearch;
use SilverStripe\Forum\Search\ForumSearchProvider;

/**
 * Forum Search.
 *
 * Wrapper for providing search functionality
 *
 * @package forum
 */

class ForumSearch
{
    
    /**
     * The search class engine to use for the forum. By default use the standard
     * Database Search but optionally allow other search engines. Must implement
     * the {@link ForumSearch} interface.
     *
     * @var String
     */
    private static $search_engine = ForumDatabaseSearch::class;
    
    /**
     * Set the search class to use for the Forum search. Must implement the
     * {@link ForumSearch} interface
     *
     * @param String
     *
     * @return The result of load() on the engine
     */
    public static function set_search_engine($engine)
    {
        if (!$engine) {
            $engine = ForumDatabaseSearch::class;
        }
        
        $search = new $engine();
        
        if ($search instanceof ForumSearchProvider) {
            self::$search_engine = $engine;
            
            return $search->load();
        } else {
            user_error("$engine must implement the " . ForumSearchProvider::class . " interface");
        }
    }
    
    /**
     * Return the search class for the forum search
     *
     * @return String
     */
    public static function get_search_engine()
    {
        return self::$search_engine;
    }
}
