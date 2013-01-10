<?php
/*
 *    Copyright 2008-2009 Laurent Eschenauer and Alard Weisscher
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *  
 */
class StoryController extends BaseController
{	
    protected $_application;
    	    
    public function indexAction() {
    	$this->_forward("view");
    }
    
    public function editAction() {
    	$this->_forward("view", "story", "public", array('mode' => 'edit'));
    }
    
    public function mapAction() {
		$story_id 		= $this->getRequest()->getParam("id");

    	//Verify if the requested user exist
		$stories 	= new Stories();
		$story 		= $stories->getStory($story_id);

		// If not, then return to the home page with an error
		if (!$story) {
			throw new Stuffpress_NotFoundException("Story $story_id does not exist");
		}

		//Get the story owner data for user properties
		$users 		= new Users();
		$user 		= $users->getUser($story->user_id);
		
		// Prepare the view
		$this->view->story_id = $story_id;
		$this->view->username = $user->username;
		$this->_helper->layout->setlayout('story_mapcontainer');
    }
    
   	public function viewAction() {
		// Get another layout
   		$this->_helper->layout->setlayout('story');
   		
		// Get, check and setup the parameters
		$story_id 		= $this->getRequest()->getParam("id");
		$page 			= $this->getRequest()->getParam("page");
		$page			= ($page == '') ? 'cover' : $page;
		$mode			= $this->getRequest()->getParam("mode");
		$embed			= $this->getRequest()->getParam("embed");
		$length 		= 8;
		
		//Verify if the requested user exist
		$stories 	= new Stories();
		$story 		= $stories->getStory($story_id);

		// If not, then return to the home page with an error
		if (!$story) {
			throw new Stuffpress_NotFoundException("Story $story_id does not exist");
		}
		
		//Get the story owner data for user properties
		$users 		= new Users();
		$user 		= $users->getUser($story->user_id);
		
		// Load the user properties
		$properties = new Properties(array(Stuffpress_Db_Properties::KEY => $user->id));
		
   		// Are we the owner ?
		if (!$embed && $this->_application->user && ($this->_application->user->id == $story->user_id)) {
			$owner = true;
		}
		else {
			$owner = false;
		}
		
		// If the page is private, go back with an error
		if (!$owner && $properties->getProperty('is_private')) {
			throw new Stuffpress_AccessDeniedException("This page has been set as private.");
		}

		// If the story is draft, go back with an error
		if (!$owner && $story->is_hidden) {
			throw new Stuffpress_AccessDeniedException("This story has not been published yet.");
		}

		// Are we in edit mode ?
		if ($owner && $mode == 'edit') {
			$edit = true;
		}
		else {
			$edit = false;
		}
		
		$data 		 = new StoryItems();
		$count		 = $data->getItemsCount($story_id, $edit);
		$pages		 = ceil($count / $length);
		
		// Now we can check if the page number is valid
		if ($page != 'cover') {
			if ($page < 0) {
				$page = 0;
			}
			else if ($page >= $pages) {
				$page = $pages - 1;
			}
		}
		
		// If page is not a cover, get the items
		if ($page != 'cover') {
			$this->view->items = $data->getItems($story_id, $length, $page * $length, $edit);
		} 
		
		// Add the data required by the view
		$this->view->embed			= $embed;
		$this->view->username 		= $user->username;
		$this->view->edit			= $edit;
		$this->view->owner			= $owner;
		$this->view->image			= $story->thumbnail;
		$this->view->user_id 	 	= $user->id;
		$this->view->story_id    	= $story->id;
		$this->view->story_title    = $story->title;
		$this->view->story_subtitle = $story->subtitle;
		$this->view->is_private		= $story->is_hidden;	
		$this->view->is_geo			= $stories->isGeo($story_id);		
	
		// Navigation options
		$this->view->page		 = $page;
		$this->view->pages		 = $pages;
		
		// Add a previous button
		$e		= $embed ? "embed/$embed/" : "";
		if ($page == 'cover') {
			unset($this->view->previous);
		}
		else if ($page == 0) {
			$action	= $edit ? "edit" : "view";
			$this->view->previous = "story/$action/id/$story_id/page/cover/$e";
		}
		else if ($page != 'cover' && $page>0) {
			$action	= $edit ? "edit" : "view";
			$this->view->previous = "story/$action/id/$story_id/page/". ($page - 1). "/$e";
		}

		// Add a next button
   		if ($page == 'cover') {
			$action	= $edit ? "edit" : "view";
			$this->view->next = "story/$action/id/$story_id/page/0/$e";			
		}
		else if (($page + 1) < $pages) {
			$action	= $edit ? "edit" : "view";
			$this->view->next = "story/$action/id/$story_id/page/".($page + 1) . "/$e";
		}
		
		// Prepare the generic view
		// Set the timezone to the user timezone
		$timezone =  $story->timezone ? $story->timezone : $properties->getProperty('timezone');
		
		date_default_timezone_set($timezone);

		// User provided footer (e.g. tracker)
		$user_footer					= $properties->getProperty('footer');
		$this->view->user_footer 		= $user_footer;
		
		// Javascript
		$this->view->headScript()->appendFile('js/prototype/prototype.js');
		$this->view->headScript()->appendFile('js/scriptaculous/scriptaculous.js');	
		$this->view->headScript()->appendFile('js/storytlr/validateForm.js');
		$this->view->headScript()->appendFile('js/controllers/story.js');
		
		// Page title
		$this->view->headTitle($story->title . " | " .$story->subtitle);
		
		// Change layout if embedding
		if ($embed) {
			$this->_helper->layout->setlayout('embed_story');	
		}
		// Page layout
		$this->view->title				= $properties->getProperty('title');
		$this->view->subtitle			= $properties->getProperty('subtitle');
		$this->view->footer				= $properties->getProperty('user_footer');
		$this->view->section			= "story";
	}
}
