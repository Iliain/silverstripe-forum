<?php

namespace SilverStripe\Forum\Pages;

use Page;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Group;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Dev\SapphireTest;
use Silverstripe\Forum\Model\Post;
use SilverStripe\Forms\HeaderField;
use Silverstripe\Forum\Pages\Forum;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Authenticator;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forum\Model\ForumCategory;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldViewButton;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use Silverstripe\Forum\Controllers\ForumHolderController;

/**
 * ForumHolder represents the top forum overview page. Its children
 * should be Forums. On this page you can also edit your global settings
 * for the entire forum.
 *
 * @package forum
 */

class ForumHolder extends Page
{
    private static $table_name = 'Forum_ForumHolder';

    private static $avatars_folder = 'forum/avatars/';

    private static $attachments_folder = 'forum/attachments/';

    private static $db = array(
        "HolderSubtitle" => "Varchar(200)",
        "ProfileSubtitle" => "Varchar(200)",
        "ForumSubtitle" => "Varchar(200)",
        "HolderAbstract" => "HTMLText",
        "ProfileAbstract" => "HTMLText",
        "ForumAbstract" => "HTMLText",
        "ProfileModify" => "HTMLText",
        "ProfileAdd" => "HTMLText",
        "DisplaySignatures" => "Boolean",
        "ShowInCategories" => "Boolean",
        "AllowGravatars" => "Boolean",
        "GravatarType" => "Varchar(10)",
        "ForbiddenWords" => "Text",
        "CanPostType" => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'LoggedInUsers')",
    );

    private static $has_one = array();

    private static $has_many = array(
        "Categories" => ForumCategory::class
    );

    private static $allowed_children = array(Forum::class);

    private static $defaults = array(
        "HolderSubtitle" => "Welcome to our forum!",
        "ProfileSubtitle" => "Edit Your Profile",
        "ForumSubtitle" => "Start a new topic",
        "HolderAbstract" => "<p>If this is your first visit, you will need to <a class=\"broken\" title=\"Click here to register\" href=\"ForumMemberProfile/register\">register</a> before you can post. However, you can browse all messages below.</p>",
        "ProfileAbstract" => "<p>Please fill out the fields below. You can choose whether some are publically visible by using the checkbox for each one.</p>",
        "ForumAbstract" => "<p>From here you can start a new topic.</p>",
        "ProfileModify" => "<p>Thanks, your member profile has been modified.</p>",
        "ProfileAdd" => "<p>Thanks, you are now signed up to the forum.</p>",
    );

    /**
     * If the user has spam protection enabled and setup then we can provide spam
     * prevention for the forum. This stores whether we actually want the registration
     * form to have such protection
     *
     * @var bool
     */
    public static $use_spamprotection_on_register = true;

    /**
     * If the user has spam protection enabled and setup then we can provide spam
     * prevention for the forum. This stores whether we actually want the posting
     * form (adding, replying) to have such protection
     *
     * @var bool
     */
    public static $use_spamprotection_on_posts = false;

    /**
     * Add a hidden field to the form which should remain empty
     * If its filled out, we can assume that a spam bot is auto-filling fields.
     *
     * @var bool
     */
    public static $use_honeypot_on_register = false;

    /**
     * @var bool If TRUE, each logged in Member who visits a Forum will write the LastViewed field
     * which is for the "Currently online" functionality.
     */
    private static $currently_online_enabled = true;

    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(function ($fields) use ($self) {

            $fields->addFieldsToTab("Root.Messages", array(
                TextField::create("HolderSubtitle", "Forum Holder Subtitle"),
                HTMLEditorField::create("HolderAbstract", "Forum Holder Abstract"),
                TextField::create("ProfileSubtitle", "Member Profile Subtitle"),
                HTMLEditorField::create("ProfileAbstract", "Member Profile Abstract"),
                TextField::create("ForumSubtitle", "Create topic Subtitle"),
                HTMLEditorField::create("ForumAbstract", "Create topic Abstract"),
                HTMLEditorField::create("ProfileModify", "Create message after modifing forum member"),
                HTMLEditorField::create("ProfileAdd", "Create message after adding forum member")
            ));
            $fields->addFieldsToTab("Root.Settings", array(
                CheckboxField::create("DisplaySignatures", "Display Member Signatures?"),
                CheckboxField::create("ShowInCategories", "Show Forums In Categories?"),
                CheckboxField::create("AllowGravatars", "Allow <a href='http://www.gravatar.com/' target='_blank'>Gravatars</a>?"),
                DropdownField::create("GravatarType", "Gravatar Type", array(
                    "standard" => _t('Forum.STANDARD', 'Standard'),
                    "identicon" => _t('Forum.IDENTICON', 'Identicon'),
                    "wavatar" => _t('Forum.WAVATAR', 'Wavatar'),
                    "monsterid" => _t('Forum.MONSTERID', 'Monsterid'),
                    "retro" => _t('Forum.RETRO', 'Retro'),
                    "mm" => _t('Forum.MM', 'Mystery Man'),
                ))->setEmptyString('Use Forum Default')
            ));

            // add a grid field to the category tab with all the categories
            $categoryConfig = GridFieldConfig::create()
                ->addComponents(
                    new GridFieldSortableHeader(),
                    new GridFieldButtonRow(),
                    new GridFieldDataColumns(),
                    new GridFieldEditButton(),
                    new GridFieldViewButton(),
                    new GridFieldDeleteAction(),
                    new GridFieldAddNewButton('buttons-before-left'),
                    new GridFieldPaginator(),
                    new GridFieldDetailForm()
                );

            $categories = GridField::create(
                'Category',
                _t('Forum.FORUMCATEGORY', 'Forum Category'),
                $self->Categories(),
                $categoryConfig
            );

            $fields->addFieldsToTab("Root.Categories", $categories);


            $fields->addFieldsToTab("Root.LanguageFilter", array(
                TextField::create("ForbiddenWords", "Forbidden words (comma separated)"),
                LiteralField::create("FWLabel", "These words will be replaced by an asterisk")
            ));

            $fields->addFieldToTab("Root.Access", HeaderField::create('SilverStripe\\Forum\\ForumHeader', _t('Forum.ACCESSPOST', 'Who can post to the forum?'), 2));
            $fields->addFieldToTab("Root.Access", OptionsetField::create("CanPostType", "", array(
                "Anyone" => _t('Forum.READANYONE', 'Anyone'),
                "LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
                "NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
            )));
        });

        $fields = parent::getCMSFields();

        return $fields;
    }

    public function canPost($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($this->CanPostType == "NoOne") {
            return false;
        }

        if ($this->CanPostType == "Anyone" || $this->canEdit($member)) {
            return true;
        }

        if ($member) {
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
     * Ensure that any categories that exist with no forum holder are updated to be owned by the first forum holder
     * if there is one. This is required now that multiple forum holds are allowed, and categories belong to holders.
     *
     * @see sapphire/core/model/DataObject#requireDefaultRecords()
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $forumCategories = ForumCategory::get()->filter('ForumHolderID', 0);
        if (!$forumCategories->exists()) {
            return;
        }

        $forumHolder = ForumHolder::get()->first();
        if (!$forumHolder) {
            return;
        }

        foreach ($forumCategories as $forumCategory) {
            $forumCategory->ForumHolderID = $forumHolder->ID;
            $forumCategory->write();
        }
    }

    /**
     * If we're on the search action, we need to at least show
     * a breadcrumb to get back to the ForumHolder page.
     * @return string
     */
    public function Breadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false, $delimiter = '&raquo;')
    {
        if (isset($this->urlParams['Action'])) {
            switch ($this->urlParams['Action']) {
                case 'search':
                    return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('SEARCHBREADCRUMB', 'Search');
                case 'memberlist':
                    return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('MEMBERLIST', 'Member List');
                case 'popularthreads':
                    return '<a href="' . $this->Link() . '">' . $this->Title . '</a> &raquo; ' . _t('MOSTPOPULARTHREADS', 'Most popular threads');
            }
        }
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
            $sqlQuery->addInnerJoin('SiteTree', '"Forum_Post"."ForumID" = "SiteTree"."ID"');
            $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
            $sqlQuery->addWhere('"SiteTree"."ParentID" = ' . $this->ID);
            return $sqlQuery->execute()->value();
    }


    /**
     * Get the number of total topics (threads)
     *
     * @return int Returns the number of topics (threads)
     */
    public function getNumTopics()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT(DISTINCT("ThreadID"))');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addInnerJoin('SiteTree', '"Forum_Post"."ForumID" = "SiteTree"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"SiteTree"."ParentID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }


    /**
     * Get the number of distinct authors
     *
     * @return int Returns the number of distinct authors
     */
    public function getNumAuthors()
    {
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('"Forum_Post"');
        $sqlQuery->setSelect('COUNT(DISTINCT("AuthorID"))');
        $sqlQuery->addInnerJoin('Member', '"Forum_Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addInnerJoin('SiteTree', '"Forum_Post"."ForumID" = "SiteTree"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"SiteTree"."ParentID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Is the "Currently Online" functionality enabled?
     * @return bool
     */
    public function CurrentlyOnlineEnabled()
    {
        return $this->config()->currently_online_enabled;
    }

    /**
     * Get a list of currently online users (last 15 minutes)
     * that belong to the "forum-members" code {@link Group}.
     *
     * @return DataList of {@link Member} objects
     */
    public function CurrentlyOnline()
    {
        if (!$this->CurrentlyOnlineEnabled()) {
            return false;
        }

        $groupIDs = array();

        if ($forumGroup = Group::get()->filter('Code', 'forum-members')->first()) {
            $groupIDs[] = $forumGroup->ID;
        }

        if ($adminGroup = Group::get()->filter('Code', array('administrators', 'Administrators'))->first()) {
            $groupIDs[] = $adminGroup->ID;
        }

        return Member::get()
            ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
            ->filter('GroupID', $groupIDs)
            ->where('"Member"."LastViewed" > ' . DB::get_conn()->datetimeIntervalClause('NOW', '-15 MINUTE'))
            ->sort('"Member"."FirstName", "Member"."Surname"');
    }

    /**
     * @deprecated 0.5
     */
    public function LatestMember($limit = 1)
    {
        user_error('Please use LatestMembers($limit) instead of LatestMember', E_USER_NOTICE);

        return $this->LatestMembers($limit);
    }

    /**
     * Get the latest members from the forum group.
     *
     * @param int $limit Number of members to return
     * @return ArrayList
     */
    public function getLatestMembers($limit = null)
    {
        if (!is_null($limit)) {
            Deprecation::notice('1.0', '$limit parameter is deprecated, please chain the limit clause');
        }
        $groupID = DB::query('SELECT "ID" FROM "Group" WHERE "Code" = \'forum-users\'')->value();

        // if we're just looking for a single MemberID, do a quicker query on the join table.
        if ($limit == 1) {
            $latestMemberId = DB::query(sprintf(
                'SELECT MAX("MemberID")
				FROM "Group_Members"
				WHERE "Group_Members"."GroupID" = \'%s\'',
                $groupID
            ))->value();

            $latestMembers = Member::get()->byId($latestMemberId);
        } else {
            $latestMembers = Member::get()
                ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                ->filter('GroupID', $groupID)
                ->sort('"Member"."ID" DESC');
            if ($limit) {
                $latestMembers = $latestMembers->limit($limit);
            }
        }

        return $latestMembers;
    }

    /**
     * Get a list of Forum Categories
     * @return DataList
     */
    public function getShowInCategories()
    {
        $forumCategories = ForumCategory::get()->filter('ForumHolderID', $this->ID);
        $showInCategories = $this->getField('ShowInCategories');
        return $forumCategories->exists() && $showInCategories;
    }

    /**
     * Get the forums. Actually its a bit more complex than that
     * we need to group by the Forum Categories.
     *
     * @return ArrayList
     */
    public function Forums()
    {
        $categoryText = isset($_REQUEST['Category']) ? Convert::raw2xml($_REQUEST['Category']) : null;
        $holder = $this;

        if ($this->getShowInCategories()) {
            return ForumCategory::get()
                ->filter('ForumHolderID', $this->ID)
                ->filterByCallback(function ($category) use ($categoryText, $holder) {
                    // Don't include if we've specified a Category, and it doesn't match this one
                    if ($categoryText !== null && $category->Title != $categoryText) {
                        return false;
                    }

                    // Get a list of forums that live under this holder & category
                    $category->CategoryForums = Forum::get()
                        ->filter(array(
                            'CategoryID' => $category->ID,
                            'ParentID' => $holder->ID,
                            'ShowInMenus' => 1
                        ))
                        ->filterByCallback(function ($forum) {
                            return $forum->canView();
                        });

                    return $category->CategoryForums->exists();
                });
        } else {
            return Forum::get()
                ->filter(array(
                    'ParentID' => $this->ID,
//                    'ShowInMenus' => 1
                ))
                ->filterByCallback(function ($forum) {
                    return $forum->canView();
                });
        }
    }

    /**
     * A function that returns the correct base table to use for custom forum queries. It uses the getVar stage to determine
     * what stage we are looking at, and determines whether to use SiteTree or SiteTree_Live (the general case). If the stage is
     * not specified, live is assumed (general case). It is a static function so it can be used for both ForumHolder and Forum.
     *
     * @return String
     */
    static function baseForumTable()
    {
        $stage = (Controller::curr()->getRequest()) ? Controller::curr()->getRequest()->getVar('stage') : false;
        // if (!$stage) {
        //     $stage = Versioned::LIVE();
        // }

        if ((class_exists('SapphireTest', false) && SapphireTest::is_running_test())
            || $stage == "Stage"
        ) {
            return "SiteTree";
        } else {
            return "SiteTree_Live";
        }
    }


    /**
     * Is OpenID support available?
     *
     * This method checks if the {@link OpenIDAuthenticator} is available and
     * registered.
     *
     * @return bool Returns TRUE if OpenID is available, FALSE otherwise.
     */
    public function OpenIDAvailable()
    {
        if (class_exists('Authenticator') == false) {
            return false;
        }

        return Authenticator::is_registered("OpenIDAuthenticator");
    }


    /**
     * Get the latest posts
     *
     * @param int $limit Number of posts to return
     * @param int $forumID - Forum ID to limit it to
     * @param int $threadID - Thread ID to limit it to
     * @param int $lastVisit Optional: Unix timestamp of the last visit (GMT)
     * @param int $lastPostID Optional: ID of the last read post
     */
    public function getRecentPosts($limit = 50, $forumID = null, $threadID = null, $lastVisit = null, $lastPostID = null)
    {
        $filter = array();

        if ($lastVisit) {
            $lastVisit = @date('Y-m-d H:i:s', $lastVisit);
        }

        $lastPostID = (int) $lastPostID;

        // last post viewed
        if ($lastPostID > 0) {
            $filter[] = "\"Post\".\"ID\" > '". Convert::raw2sql($lastPostID) ."'";
        }

        // last time visited
        if ($lastVisit) {
            $filter[] = "\"Post\".\"Created\" > '". Convert::raw2sql($lastVisit) ."'";
        }

        // limit to a forum
        if ($forumID) {
            $filter[] = "\"Post\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";
        }

        // limit to a thread
        if ($threadID) {
            $filter[] = "\"Post\".\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";
        }

        // limit to just this forum install
        $filter[] = "\"ForumPage\".\"ParentID\"='{$this->ID}'";

        $posts = Post::get()
            ->leftJoin('ForumThread', '"Post"."ThreadID" = "ForumThread"."ID"')
            ->leftJoin(ForumHolder::baseForumTable(), '"ForumPage"."ID" = "Post"."ForumID"', 'ForumPage')
            ->limit($limit)
            ->sort('"Post"."ID"', 'DESC')
            ->where($filter);

        $recentPosts = new ArrayList();
        foreach ($posts as $post) {
            $recentPosts->push($post);
        }
        if ($recentPosts->count() > 0) {
            return $recentPosts;
        }
        return null;
    }


    /**
     * Are new posts available?
     *
     * @param int $id
     * @param array $data Optional: If an array is passed, the timestamp of
     *                    the last created post and it's ID will be stored in
     *                    it (keys: 'last_id', 'last_created')
     * @param int $lastVisit Unix timestamp of the last visit (GMT)
     * @param int $lastPostID ID of the last read post
     * @param int $thread ID of the relevant topic (set to NULL for all
     *                     topics)
     * @return bool Returns TRUE if there are new posts available, otherwise
     *              FALSE.
     */
    public static function new_posts_available($id, &$data = array(), $lastVisit = null, $lastPostID = null, $forumID = null, $threadID = null)
    {
        $filter = array();

        // last post viewed
        $filter[] = "\"ForumPage\".\"ParentID\" = '". Convert::raw2sql($id) ."'";
        if ($lastPostID) {
            $filter[] = "\"Post\".\"ID\" > '". Convert::raw2sql($lastPostID) ."'";
        }
        if ($lastVisit) {
            $filter[] = "\"Post\".\"Created\" > '". Convert::raw2sql($lastVisit) ."'";
        }
        if ($forumID) {
            $filter[] = "\"Post\".\"ForumID\" = '". Convert::raw2sql($forumID) ."'";
        }
        if ($threadID) {
            $filter[] = "\"ThreadID\" = '". Convert::raw2sql($threadID) ."'";
        }

        $filter = implode(" AND ", $filter);

        $version = DB::query("
			SELECT MAX(\"Post\".\"ID\") AS \"LastID\", MAX(\"Post\".\"Created\") AS \"LastCreated\"
			FROM \"Post\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" AS \"ForumPage\" ON \"Post\".\"ForumID\"=\"ForumPage\".\"ID\"
			WHERE $filter")->first();

        if ($version == false) {
            return false;
        }

        if ($data) {
            $data['last_id'] = (int)$version['LastID'];
            $data['last_created'] = strtotime($version['LastCreated']);
        }

        $lastVisit = (int) $lastVisit;

        if ($lastVisit <= 0) {
            $lastVisit = false;
        }

        $lastPostID = (int)$lastPostID;
        if ($lastPostID <= 0) {
            $lastPostID = false;
        }

        if (!$lastVisit && !$lastPostID) {
            return true;
        }
        if ($lastVisit && (strtotime($version['LastCreated']) > $lastVisit)) {
            return true;
        }

        if ($lastPostID && ((int)$version['LastID'] > $lastPostID)) {
            return true;
        }

        return false;
    }

    /**
     * Helper Method from the template includes. Uses $ForumHolder so in order for it work
     * it needs to be included on this page
     *
     * @return ForumHolder
     */
    public function getForumHolder()
    {
        return $this;
    }

    public function getControllerName()
    {
        return ForumHolderController::class;
    }
}
