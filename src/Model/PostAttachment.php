<?php

namespace SilverStripe\Forum\Model;

use SilverStripe\Assets\File;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPRequest;

/**
 * Attachments for posts (one post can have many attachments)
 *
 * @package forum
 */
class PostAttachment extends File
{
    private static $table_name = 'Forum_PostAttachment';

    private static $has_one = array(
        "Post" => Post::class
    );

    private static $defaults = array(
        'ShowInSearch' => 0
    );

    /**
     * Can a user delete this attachment
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return ($this->Post()) ? $this->Post()->canDelete($member) : true;
    }

    /**
     * Can a user edit this attachement
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return ($this->Post()) ? $this->Post()->canEdit($member) : true;
    }

    /**
     * Allows the user to download a file without right-clicking
     */
    public function download()
    {
        if (isset($this->urlParams['ID'])) {
            $SQL_ID = Convert::raw2sql($this->urlParams['ID']);

            if (is_numeric($SQL_ID)) {
                $file = DataObject::get_by_id(PostAttachment::class, $SQL_ID);
                $response = HTTPRequest::send_file(file_get_contents($file->getFullPath()), $file->Name);
                $response->output();
            }
        }

        return $this->redirectBack();
    }
}
