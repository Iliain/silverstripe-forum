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
 * Member Signups Report.
 * Lists the Number of people who have signed up in the past months categorized
 * by month.
 */
class ForumReportMemberSignups extends Report
{

    public function title()
    {
        return _t('Forum.FORUMSIGNUPS', 'Forum Signups by Month');
    }

    public function sourceRecords($params = array())
    {
        $membersQuery = new SQLSelect();
        $membersQuery->setFrom('"Member"');
        $membersQuery->setSelect(array(
            'Month' => DB::get_conn()->formattedDatetimeClause('"Created"', '%Y-%m'),
            'Signups' => 'COUNT("Created")'
        ));
        $membersQuery->setGroupBy('"Month"');
        $membersQuery->setOrderBy('"Month"', 'DESC');
        $members = $membersQuery->execute();

        $output = ArrayList::create();
        foreach ($members as $member) {
            $member['Month'] = date('Y F', strtotime($member['Month']));
            $output->add(ArrayData::create($member));
        }
        return $output;
    }

    public function columns()
    {
        $fields = array(
            'Month' => 'Month',
            'Signups' => 'Signups'
        );

        return $fields;
    }

    public function group()
    {
        return 'Forum Reports';
    }
}
