<?php

namespace SilverStripe\Forum\Model;

use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Model\ForumThreadSubscription;

/**
 * Forum Thread Subscription: Allows members to subscribe to this thread
 * and receive email notifications when these topics are replied to.
 *
 * @package forum
 */
class ForumThreadSubscription extends DataObject
{
    private static $table_name = 'Forum_ForumThreadSubscription';

    private static $db = array(
        "LastSent" => "Datetime"
    );

    private static $has_one = array(
        "Thread" => ForumThread::class,
        "Member" => Member::class
    );

    /**
     * Checks to see if a Member is already subscribed to this thread
     *
     * @param int $threadID The ID of the thread to check
     * @param int $memberID The ID of the currently logged in member (Defaults to Member::currentUserID())
     *
     * @return bool true if they are subscribed, false if they're not
     */
    static function already_subscribed($threadID, $memberID = null)
    {
        if (!$memberID) {
            $memberID = Member::currentUserID();
        }
        $SQL_threadID = Convert::raw2sql($threadID);
        $SQL_memberID = Convert::raw2sql($memberID);

        if ($SQL_threadID=='' || $SQL_memberID=='') {
            return false;
        }

        return (DB::query("
			SELECT COUNT(\"ID\")
			FROM \"Forum_ForumThreadSubscription\"
			WHERE \"ThreadID\" = '$SQL_threadID' AND \"MemberID\" = $SQL_memberID")->value() > 0) ? true : false;
    }

    /**
     * Notifies everybody that has subscribed to this topic that a new post has been added.
     * To get emailed, people subscribed to this topic must have visited the forum
     * since the last time they received an email
     *
     * @param Post $post The post that has just been added
     */
    static function notify(Post $post)
    {
        $list = DataObject::get(
            ForumThreadSubscription::class,
            "\"ThreadID\" = '". $post->ThreadID ."' AND \"MemberID\" != '$post->AuthorID'"
        );

        if ($list) {
            foreach ($list as $obj) {
                $SQL_id = Convert::raw2sql((int)$obj->MemberID);

                // Get the members details
                $member = DataObject::get_one(Member::class, "\"Member\".\"ID\" = '$SQL_id'");
                $adminEmail = Config::inst()->get(Email::class, 'admin_email');

                if ($member) {
                    $email = new Email();
                    $email->setFrom($adminEmail);
                    $email->setTo($member->Email);
                    $email->setSubject(_t('Post.NEWREPLY', 'New reply for {title}', array('title' => $post->Title)));
                    $email->setHTMLTemplate('Email/ForumMember_TopicNotification');
                    $email->setData($member);
                    $email->setData($post);
                    $email->setData([
                        'UnsubscribeLink' => Director::absoluteBaseURL() . $post->Thread()->Forum()->Link() . '/unsubscribe/' . $post->ID
                    ]);
                    $email->send();
                }
            }
        }
    }
}
