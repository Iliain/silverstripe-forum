<?php

namespace SilverStripe\Forum\Pages;

use Page;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Group;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Pages\ForumHolder;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forum\Model\ForumCategory;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forum\Controllers\ForumController;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

/**
 * Forum represents a collection of forum threads. Each thread is a different topic on
 * the site. You can customize permissions on a per forum basis in the CMS.
 *
 * @todo Implement PermissionProvider for editing, creating forums.
 *
 * @package forum
 */
class Forum extends Page
{
    private static $allowed_children = 'none';

    private static $table_name = 'Forum_Forum';

    private static $icon = "forum/client/images/treeicons/user-file.gif";

    /**
     * Enable this to automatically notify moderators when a message is posted
     * or edited on his forums.
     */
    static $notify_moderators = false;

    private static $db = array(
        "Abstract" => "Text",
        "CanPostType" => "Enum('Inherit, Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'Inherit')",
        "CanAttachFiles" => "Boolean",
    );

    private static $has_one = array(
        "Moderator" => Member::class,
        "Category" => ForumCategory::class
    );

    private static $many_many = array(
        'Moderators' => Member::class,
        'PosterGroups' => Group::class
    );

    private static $defaults = array(
        "ForumPosters" => "LoggedInUsers"
    );

    /**
     * Number of posts to include in the thread view before pagination takes effect.
     *
     * @var int
     */
    static $posts_per_page = 8;

    /**
     * When migrating from older versions of the forum it used post ID as the url token
     * as of forum 1.0 we now use ThreadID. If you want to enable 301 redirects from post to thread ID
     * set this to true
     *
     * @var bool
     */
    static $redirect_post_urls_to_thread = false;

    /**
     * Check if the user can view the forum.
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return (parent::canView($member) || $this->canModerate($member));
    }

    /**
     * Check if the user can post to the forum and edit his own posts.
     */
    public function canPost($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($this->CanPostType == "Inherit") {
            $holder = $this->getForumHolder();

            if ($holder) {
                return $holder->canPost($member);
            }

            return false;
        }

        if ($this->CanPostType == "NoOne") {
            return false;
        }

        if ($this->CanPostType == "Anyone" || $this->canEdit($member)) {
            return true;
        }

        if ($member = Security::getCurrentUser()) {
            if ($member->IsSuspended()) {
                return false;
            }
            if ($member->IsBanned()) {
                return false;
            }

            if ($this->CanPostType == "LoggedInUsers") {
                return true;
            }

            if ($groups = $this->PosterGroups()) {
                foreach ($groups as $group) {
                    if ($member->inGroup($group)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if user has access to moderator panel and can delete posts and threads.
     */
    public function canModerate($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (!$member) {
            return false;
        }

        // Admins
        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }

        // Moderators
        if ($member->isModeratingForum($this)) {
            return true;
        }

        return false;
    }

    /**
     * Can we attach files to topics/posts inside this forum?
     *
     * @return bool Set to TRUE if the user is allowed to, to FALSE if they're
     *              not
     */
    public function canAttach($member = null)
    {
        return $this->CanAttachFiles ? true : false;
    }

    // public function requireTable()
    // {
    //     // Migrate permission columns
    //     if (DB::get_conn()->hasTable('Forum')) {
    //         $fields = DB::get_conn()->fieldList('Forum');
    //         if (in_array('ForumPosters', array_keys($fields)) && !in_array('CanPostType', array_keys($fields))) {
    //             DB::get_conn()->renameField('Forum', 'ForumPosters', 'CanPostType');
    //             DB::alteration_message('Migrated forum permissions from "ForumPosters" to "CanPostType"', "created");
    //         }
    //     }

    //     parent::requireTable();
    // }

    /**
     * Add default records to database
     *
     * This function is called whenever the database is built, after the
     * database tables have all been created.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $code = "ACCESS_FORUM";
        if (!($forumGroup = Group::get()->filter('Code', 'forum-members')->first())) {
            $group = new Group();
            $group->Code = 'forum-members';
            $group->Title = "Forum Members";
            $group->write();

            Permission::grant($group->ID, $code);
            DB::alteration_message(_t('Forum.GROUPCREATED', 'Forum Members group created'), 'created');
        } elseif (!Permission::get()->filter(array('GroupID' => $forumGroup->ID, 'Code' => $code))->exists()) {
            Permission::grant($forumGroup->ID, $code);
        }

        if (!($category = ForumCategory::get()->first())) {
            $category = new ForumCategory();
            $category->Title = _t('Forum.DEFAULTCATEGORY', 'General');
            $category->write();
        }

        if (!ForumHolder::get()->exists()) {
            $forumholder = new ForumHolder();
            $forumholder->Title = "Forums";
            $forumholder->URLSegment = "forums";
            $forumholder->Content = "<p>"._t('Forum.WELCOMEFORUMHOLDER', 'Welcome to SilverStripe Forum Module! This is the default ForumHolder page. You can now add forums.')."</p>";
            $forumholder->Status = "Published";
            $forumholder->write();
            $forumholder->publish("Stage", "Live");
            DB::alteration_message(_t('Forum.FORUMHOLDERCREATED', 'ForumHolder page created'), "created");

            $forum = new Forum();
            $forum->Title = _t('Forum.TITLE', 'General Discussion');
            $forum->URLSegment = "general-discussion";
            $forum->ParentID = $forumholder->ID;
            $forum->Content = "<p>"._t('Forum.WELCOMEFORUM', 'Welcome to SilverStripe Forum Module! This is the default Forum page. You can now add topics.')."</p>";
            $forum->Status = "Published";
            $forum->CategoryID = $category->ID;
            $forum->write();
            $forum->publish("Stage", "Live");

            DB::alteration_message(_t('Forum.FORUMCREATED', 'Forum page created'), "created");
        }
    }

    /**
     * Check if we can and should show forums in categories
     */
    public function getShowInCategories()
    {
        $holder = $this->getForumHolder();
        if ($holder) {
            return $holder->getShowInCategories();
        }
    }

    /**
     * Returns a FieldList with which to create the CMS editing form
     *
     * @return FieldList The fields to be displayed in the CMS.
     */
    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(function ($fields) use ($self) {
            // Requirements::javascript("forum/javascript/ForumAccess.js");
            // Requirements::css("forum/css/Forum_CMS.css");

            $fields->addFieldToTab("Root.Access", new HeaderField('AccessHeader', _t('Forum.ACCESSPOST', 'Who can post to the forum?'), 2));
            $fields->addFieldToTab("Root.Access", $optionSetField = new OptionsetField("CanPostType", "", array(
                "Inherit" => "Inherit",
                "Anyone" => _t('Forum.READANYONE', 'Anyone'),
                "LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
                "OnlyTheseUsers" => _t('Forum.READLIST', 'Only these people (choose from list)'),
                "NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
            )));

            $optionSetField->addExtraClass('ForumCanPostTypeSelector');

            $fields->addFieldsToTab("Root.Access", array(
                new TreeMultiselectField("PosterGroups", _t('Forum.GROUPS', "Groups")),
                new OptionsetField("CanAttachFiles", _t('Forum.ACCESSATTACH', 'Can users attach files?'), array(
                    "1" => _t('Forum.YES', 'Yes'),
                    "0" => _t('Forum.NO', 'No')
                ))
            ));


            //Dropdown of forum category selection.
            $categories = ForumCategory::get()->map();

            $fields->addFieldsToTab(
                "Root.Main",
                DropdownField::create('CategoryID', _t('Forum.FORUMCATEGORY', 'Forum Category'), $categories),
                'Content'
            );

            //GridField Config - only need to attach or detach Moderators with existing Member accounts.
            $moderatorsConfig = GridFieldConfig::create()
                ->addComponent(new GridFieldButtonRow('before'))
                ->addComponent(new GridFieldAddExistingAutocompleter('buttons-before-right'))
                ->addComponent(new GridFieldToolbarHeader())
                ->addComponent($sort = new GridFieldSortableHeader())
                ->addComponent($columns = new GridFieldDataColumns())
                ->addComponent(new GridFieldDeleteAction(true))
                ->addComponent(new GridFieldPageCount('toolbar-header-right'))
                ->addComponent($pagination = new GridFieldPaginator());

            // Use GridField for Moderator management
            $moderators = GridField::create(
                'Moderators',
                _t('MODERATORS', 'Moderators for this forum'),
                $self->Moderators(),
                $moderatorsConfig
            );

            $columns->setDisplayFields(array(
                'Nickname' => 'Nickname',
                'FirstName' => 'First name',
                'Surname' => 'Surname',
                'Email'=> 'Email',
                'LastVisited.Long' => 'Last Visit'
            ));

            $sort->setThrowExceptionOnBadDataType(false);
            $pagination->setThrowExceptionOnBadDataType(false);

            $fields->addFieldToTab('Root.Moderators', $moderators);
        });

        $fields = parent::getCMSFields();

        return $fields;
    }

    /**
     * Create breadcrumbs
     *
     * @param int $maxDepth Maximal lenght of the breadcrumb navigation
     * @param bool $unlinked Set to TRUE if the breadcrumb should consist of
     *                       links, otherwise FALSE.
     * @param bool $stopAtPageType Currently not used
     * @param bool $showHidden Set to TRUE if also hidden pages should be
     *                         displayed
     * @return string HTML code to display breadcrumbs
     */
    public function Breadcrumbs($maxDepth = null, $unlinked = false, $stopAtPageType = false, $showHidden = false, $delimiter = '&raquo;')
    {
        $page = $this;
        $nonPageParts = array();
        $parts = array();

        $controller = Controller::curr();
        $params = $controller->getURLParams();

        $forumThreadID = $params['ID'];
        if (is_numeric($forumThreadID)) {
            if ($topic = ForumThread::get()->byID($forumThreadID)) {
                $nonPageParts[] = Convert::raw2xml($topic->getTitle());
            }
        }

        while ($page && (!$maxDepth || sizeof($parts) < $maxDepth)) {
            if ($showHidden || $page->ShowInMenus || ($page->ID == $this->ID)) {
                if ($page->URLSegment == 'home') {
                    $hasHome = true;
                }

                if ($nonPageParts) {
                    $parts[] = '<a href="' . $page->Link() . '">' . Convert::raw2xml($page->Title) . '</a>';
                } else {
                    $parts[] = (($page->ID == $this->ID) || $unlinked)
                            ? Convert::raw2xml($page->Title)
                            : '<a href="' . $page->Link() . '">' . Convert::raw2xml($page->Title) . '</a>';
                }
            }

            $page = $page->Parent;
        }

        return implode(" &raquo; ", array_reverse(array_merge($nonPageParts, $parts)));
    }

    /**
     * Helper Method from the template includes. Uses $ForumHolder so in order for it work
     * it needs to be included on this page
     *
     * @return ForumHolder
     */
    public function getForumHolder()
    {
        $holder = $this->Parent();
        if ($holder->ClassName == ForumHolder::class) {
            return $holder;
        }
    }

    /**
     * Get the latest posting of the forum. For performance the forum ID is stored on the
     * {@link Post} object as well as the {@link Forum} object
     *
     * @return Post
     */
    public function getLatestPost()
    {
        return Post::get()->filter('ForumID', $this->ID)->sort('"Forum_Post"."ID" DESC')->first();
    }

    /**
     * Get the number of total topics (threads) in this Forum
     *
     * @return int Returns the number of topics (threads)
     */
    public function getNumTopics()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT(DISTINCT("ThreadID"))');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ForumID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Get the number of total posts
     *
     * @return int Returns the number of posts
     */
    public function getNumPosts()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT("Forum_Post"."ID")');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ForumID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }


    /**
     * Get the number of distinct Authors
     *
     * @return int
     */
    public function getNumAuthors()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT(DISTINCT("AuthorID"))');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ForumID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Returns the Topics (the first Post of each Thread) for this Forum
     * @return DataList
     */
    public function getTopics()
    {
        // Get a list of Posts
        $posts = Post::get();

        // Get the underlying query and change it to return the ThreadID and Max(Created) and Max(ID) for each thread
        // of those posts
        $postQuery = $posts->dataQuery()->query();

        $postQuery
            ->setSelect(array())
            ->selectField('MAX("Forum_Post"."Created")', 'PostCreatedMax')
            ->selectField('MAX("Forum_Post"."ID")', 'PostIDMax')
            ->selectField('"ThreadID"')
            ->setGroupBy('"ThreadID"')
            ->addWhere(sprintf('"ForumID" = \'%s\'', $this->ID))
            ->setDistinct(false);

        // Get a list of forum threads inside this forum that aren't sticky
        $threads = ForumThread::get()->filter(array(
            'ForumID' => $this->ID,
            'IsGlobalSticky' => 0,
            'IsSticky' => 0
        ));


        // Get the underlying query and change it to inner join on the posts list to just show threads that
        // have approved (and maybe awaiting) posts, and sort the threads by the most recent post
        $threadQuery = $threads->dataQuery()->query();
        $threadQuery
            ->addSelect(array('"PostMax"."PostCreatedMax", "PostMax"."PostIDMax"'))
            ->addFrom('INNER JOIN ('.$postQuery->sql().') AS "PostMax" ON ("PostMax"."ThreadID" = "Forum_ForumThread"."ID")')
            ->addOrderBy(array('"PostMax"."PostCreatedMax" DESC', '"PostMax"."PostIDMax" DESC'))
            ->setDistinct(false);

        // Alter the forum threads list to use the new query
        // $threads = $threads->setDataQuery(new ForumDataQuery('ForumThread', $threadQuery));

        // And return the results
        if ($threads->count()){
            return $threads->exists() ? new PaginatedList($threads, $_GET) : null;
        }
    }

    /*
	 * Returns the Sticky Threads
	 * @param boolean $include_global Include Global Sticky Threads in the results (default: true)
	 * @return DataList
	 */
    public function getStickyTopics($include_global = true)
    {
        // Get Threads that are sticky & in this forum
        $where = '("Forum_ForumThread"."ForumID" = '.$this->ID.' AND "Forum_ForumThread"."IsSticky" = 1)';
        // Get Threads that are globally sticky
        if ($include_global) {
            $where .= ' OR ("Forum_ForumThread"."IsGlobalSticky" = 1)';
        }

        // Get the underlying query
        $query = ForumThread::get()
	        ->where($where)
//			->leftJoin()
	        ->dataQuery()->query();

        // Sort by the latest Post in each thread's Created date
        $query
          ->addSelect('"PostMax"."PostMax"')
          // TODO: Confirm this works in non-MySQL DBs

            ->addLeftJoin('(SELECT MAX("Created") AS "PostMax", "ThreadID" FROM "Forum_Post" WHERE "ForumID" = \'%s\' GROUP BY "ThreadID") AS "PostMax"', '"PostMax"."ThreadID" = "Forum_ForumThread"."ID"')
            ->addOrderBy('"PostMax"."PostMax" DESC')
            ->setDistinct(false);

       // Build result as ArrayList
        $res = new ArrayList();
        $rows = $query->execute();
//        $rows = [];
        if ($rows) {
            foreach ($rows as $row) {
                $res->push(new ForumThread($row));
            }
        }

        return $res;
    }

    public function getControllerName()
    {
        return ForumController::class;
    }
}
