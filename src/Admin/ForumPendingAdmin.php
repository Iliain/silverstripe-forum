<?php

namespace SilverStripe\Forum\Admin;

use SilverStripe\Security\Group;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forum\Model\Post;
use SilverStripe\ORM\DataObject;

class ForumPendingAdmin {

}

// class ForumPendingAdmin extends ModelAdmin 
// {
	// private static $menu_icon = 'silverstripe/silverstripe-forum:client/images/treeicons/user';

	// private static $title       = 'Pending Forum';

	// private static $menu_title  = 'Pending Forum';

	// private static $url_segment = 'forum';

	// private static $managed_models  = [
    //     Post::class,
    //     Group::class
    // ];

	// private static $model_importers = [];
	
	// public function getEditForm($id = null, $fields = null) 
    // {
	// 	$form = parent::getEditForm($id, $fields);
	
	// 	$gridFieldName = $this->sanitiseClassName($this->modelClass);
	// 	$gridFieldFields = $form->Fields();
		
	// 	$gridField = $gridFieldFields->fieldByName($gridFieldName);
	
	// 	// modify the list view.
	// 	$gridFieldConfig = $gridField->getConfig();
	
	// 	$gridFieldConfig->removeComponentsByType('GridFieldAddNewButton');
	// 	$gridFieldConfig->removeComponentsByType('GridFieldPrintButton');
	// 	$gridFieldConfig->removeComponentsByType('GridFieldExportButton');
		
	// 	if ($this->modelClass == Post::class) {
	// 		// $gridFieldConfig->addComponent(new GridField_ApprovePostAction());
	// 	}
		
	// 	if ($this->modelClass == Group::class) {
	// 		//Remove the delete button
	// 		$gridFieldConfig->removeComponentsByType('GridFieldDeleteAction');
			
	// 		// Modify the detail form
	// 		$detailForm = $gridFieldConfig->getComponentByType('GridFieldDetailForm');
	// 		$detailForm->setItemEditFormCallback(function($form, $component) {
	// 			$fields = $form->Fields();
	// 			$groupId = $fields->dataFieldByName('ID')->Value();
				
	// 			$group = Group::get()->byID($groupId);
	// 			$members = $group->Members()->filter("Approved", false);

	// 			// Remove tabs
	// 			$fields->removeByName("Members");
	// 			$fields->removeByName("Permissions");
	// 			$fields->removeByName("Roles");
	// 			$fields->removeByName("Subsites");
	// 			$fields->removeByName("RestrictedArea");
	// 			$fields->removeByName("Forum");
				
	// 			// GridFieldConfig
	// 			$gridFieldConfig = GridFieldConfig_RecordEditor::create();
	// 			// $gridFieldConfig->removeComponentsByType('GridFieldAddNewButton');
	// 			// $gridFieldConfig->getComponentByType('GridFieldDataColumns')->setDisplayFields(array ('FirstName'=>'FirstName','Surname'=>'Surname','Email'=>'Email', 'Approved' => 'Approved'));
	// 			// $gridFieldConfig->addComponent(new GridField_ApproveUserAction());
	// 			// $gridFieldConfig->addComponent(new GridField_DenyUserAction());
				
	// 			//$fields->addFieldToTab("Root.Members", HeaderField::create('Test'));
	// 			$fields->addFieldToTab("Root.Members", $gridField = new GridField('Members', 'Members', $members, $gridFieldConfig));
	// 			$gridField->setForm($form);
				
	// 		});
	// 	}
		
	// 	return $form;
	// }
	
	// public function getList() 
    // {
	// 	$list = parent::getList();
	// 	$params = $this->request->requestVar('q'); // get the search params
		
	// 	// For the posts model, filter the results so only the Awaiting posts are shown
	// 	// But if a search paramater is used, show according to that
	// 	if($this->modelClass == Post::class && !(isset($params['Status']) && $params['Status'])) {
	// 		$list = $list->filter('Status', 'Awaiting');
	// 	}
		
	// 	if($this->modelClass == Group::class) {
	// 		$list = $list->filter(array('IsForumGroup' => true, 'UserModerationRequired' => true));
	// 	}
	
	// 	return $list;
	// }
// }
