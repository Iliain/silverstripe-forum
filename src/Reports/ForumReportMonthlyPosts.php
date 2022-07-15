<?php

namespace SilverStripe\Forum\Reports;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Reports\Report;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * Forum Reports.
 * These are some basic reporting tools which sit in the CMS for the user to view.
 * No fancy graphing tools or anything just some simple querys and numbers
 *
 * @package forum
 */

/**
 * Member Posts Report.
 * Lists the Number of Posts made in the forums in the past months categorized
 * by month.
 */
class ForumReportMonthlyPosts extends Report
{

    public function title()
    {
        return _t('Forum.FORUMMONTHLYPOSTS', 'Forum Posts by Month');
    }

    public function sourceRecords($params = array())
    {
        $postsQuery = new SQLSelect();
        $postsQuery->setFrom('"Post"');
        $postsQuery->setSelect(array(
            'Month' => DB::get_conn()->formattedDatetimeClause('"Created"', '%Y-%m'),
            'Posts' => 'COUNT("Created")'
        ));
        $postsQuery->setGroupBy('"Month"');
        $postsQuery->setOrderBy('"Month"', 'DESC');
        $posts = $postsQuery->execute();

        $output = ArrayList::create();
        foreach ($posts as $post) {
            $post['Month'] = date('Y F', strtotime($post['Month']));
            $output->add(ArrayData::create($post));
        }
        return $output;
    }

    public function columns()
    {
        $fields = array(
            'Month' => 'Month',
            'Posts' => 'Posts'
        );

        return $fields;
    }

    public function group()
    {
        return 'Forum Reports';
    }
}
