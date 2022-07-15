<?php

namespace SilverStripe\Forum\Model;

use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Forum\Pages\Forum;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forum\Model\ForumThreadSubscription;
use SilverStripe\Security\Security;

/**
 * A representation of a forum thread. A forum thread is 1 topic on the forum
 * which has multiple posts underneath it.
 *
 * @package forum
 */
class ForumThread extends DataObject
{
    private static $table_name = 'Forum_ForumThread';

    private static $db = array(
        "Title" => "Varchar(255)",
        "Content" => "Text",
        "NumViews" => "Int",
        "IsSticky" => "Boolean",
        "IsReadOnly" => "Boolean",
        "IsGlobalSticky" => "Boolean"
    );

    private static $has_one = array(
        'Forum' => Forum::class
    );

    private static $has_many = array(
        'Posts' => Post::class
    );

    private static $defaults = array(
        'NumViews' => 0,
        'IsSticky' => false,
        'IsReadOnly' => false,
        'IsGlobalSticky' => false
    );

    private static $indexes = array(
        'IsSticky' => true,
        'IsGlobalSticky' => true
    );

    /**
     * @var null|boolean Per-request cache, whether we should display signatures on a post.
     */
    private static $_cache_displaysignatures = null;

    /**
     * Check if the user can create new threads and add responses
     */
    public function canPost($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return ($this->Forum()->canPost($member) && !$this->IsReadOnly);
    }

    /**
     * Check if user can moderate this thread
     */
    public function canModerate($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->Forum()->canModerate($member);
    }

    /**
     * Check if user can view the thread
     */
    public function canView($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->Forum()->canView($member);
    }

    /**
     * Hook up into moderation.
     */
    public function canEdit($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->canModerate($member);
    }

    /**
     * Hook up into moderation - users cannot delete their own posts/threads because
     * we will loose history this way.
     */
    public function canDelete($member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return $this->canModerate($member);
    }

    /**
     * Hook up into canPost check
     */
    public function canCreate($member = null, $context = [])
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        return $this->canPost($member);
    }

    /**
     * Are Forum Signatures on Member profiles allowed.
     * This only needs to be checked once, so we cache the initial value once per-request.
     *
     * @return bool
     */
    public function getDisplaySignatures()
    {
        if (isset(self::$_cache_displaysignatures) && self::$_cache_displaysignatures !== null) {
            return self::$_cache_displaysignatures;
        }

        $result = $this->Forum()->Parent()->DisplaySignatures;
        self::$_cache_displaysignatures = $result;
        return $result;
    }

    /**
     * Get the latest post from this thread. Nicer way then using an control
     * from the template
     *
     * @return Post
     */
    public function getLatestPost()
    {
        return DataObject::get_one(Post::class, "\"ThreadID\" = '$this->ID'", true, '"ID" DESC');
    }

    /**
     * Return the first post from the thread. Useful to working out the original author
     *
     * @return Post
     */
    public function getFirstPost()
    {
        return DataObject::get_one(Post::class, "\"ThreadID\" = '$this->ID'", true, '"ID" ASC');
    }

    /**
     * Return the number of posts in this thread. We could use count on
     * the dataobject set but that is slower and causes a performance overhead
     *
     * @return int
     */
    public function getNumPosts()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT("Forum_Post"."ID")');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ThreadID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Check if they have visited this thread before. If they haven't increment
     * the NumViews value by 1 and set visited to true.
     *
     * @return void
     */
    public function incNumViews()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();
        if ($session->get('ForumViewed-' . $this->ID)) {
            return false;
        }

        $session->set('ForumViewed-' . $this->ID, 'true');

        $this->NumViews++;
        $SQL_numViews = Convert::raw2sql($this->NumViews);

        DB::query("UPDATE \"Forum_ForumThread\" SET \"NumViews\" = '$SQL_numViews' WHERE \"ID\" = $this->ID");
    }

    /**
     * Link to this forum thread
     *
     * @return String
     */
    public function Link($action = "show", $showID = true)
    {
        $forum = DataObject::get_by_id(Forum::class, $this->ForumID);
        if ($forum) {
            $baseLink = $forum->Link();
            $extra = ($showID) ? '/'.$this->ID : '';
            return ($action) ? $baseLink . $action . $extra : $baseLink;
        } else {
            user_error("Bad ForumID '$this->ForumID'", E_USER_WARNING);
        }
    }

    /**
     * Check to see if the user has subscribed to this thread
     *
     * @return bool
     */
    public function getHasSubscribed()
    {
        $member = Security::getCurrentUser();

        return ($member) ? ForumThreadSubscription::already_subscribed($this->ID, $member->ID) : false;
    }

    /**
     * Before deleting the thread remove all the posts
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        if ($posts = $this->Posts()) {
            foreach ($posts as $post) {
                // attachment deletion is handled by the {@link Post::onBeforeDelete}
                $post->delete();
            }
        }
    }

    public function onAfterWrite()
    {
        if ($this->isChanged('ForumID', 2)) {
            $posts = $this->Posts();
            if ($posts && $posts->count()) {
                foreach ($posts as $post) {
                    $post->ForumID=$this->ForumID;
                    $post->write();
                }
            }
        }
        parent::onAfterWrite();
    }

    /**
     * @return Text
     */
    public function getEscapedTitle()
    {
        //return DBField::create('Text', $this->dbObject('Title')->XML());
        return DBField::create_field('Text', $this->dbObject('Title')->XML());
    }
}
