<?php
/**
 * Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('CommentsController', 'Comments.Controller');
App::uses('Comment', 'Comments.Model');

if (!class_exists('User')) {
	class User extends CakeTestModel {
	/**
	 *
	 */
		public $name = 'User';
	}
}

/**
 * Test Comments Controller
 *
 * @package comments
 * @subpackage comments.tests.cases.controllers
 */
class TestCommentsController extends CommentsController {

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'RequestHandler',
		'Paginator',
		'Session');

/**
 * Auto render
 * @var boolean
 */
	public $autoRender = false;

/**
 * Rendered view
 * @var string
 */
	public $renderedView = null;

/**
 * Redirect URL
 *
 * @var mixed
 */
	public $redirectUrl = null;

/**
 * Cake error method logged when cakeError is triggered
 * @var string
 */
	public $cakeErrorMethod = null;

/**
 * Override controller method for testing
 */
	public function redirect($url, $status = null, $exit = true) {
		$this->redirectUrl = $url;
	}

/**
 * Override controller method for testing
 */
	public function render($action = null, $layout = null, $file = null) {
		$this->renderedView = $action;
	}

/**
 * Override controller method for testing
 */
	public function cakeError($method, $messages = array()) {
		$this->cakeErrorMethod = $method;
	}

}

/**
 * Comments Controller Test
 *
 * @package comments
 * @subpackage comments.tests.cases.controllers
 */
class CommentsControllerTest extends CakeTestCase {

/**
 * Controller being tested
 * @var TestCommentsController
 */
	public $Comments = null;

	public $paginate = array();

	public $components = array(
		'RequestHandler',
		'Paginator',
		'Session');

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'plugin.Comments.comment',
		'plugin.Comments.user',
		'plugin.Comments.article');

/**
 * (non-PHPdoc)
 * @see cake/tests/lib/CakeTestCase#startTest($method)
 */
	public function setUp() {
		$this->Request = new CakeRequest();
		$this->Request->params = array(
			'named' => array(),
			'pass' => array(),
			'url' => array());
		$this->Comments = new TestCommentsController($this->Request);
		$this->Comments->request->params = $this->Request->params;
		$this->Comments->constructClasses();
	}

/**
 * Test Controller instance
 *
 * @return void
 */
	public function testCommentsControllerInstance() {
		$this->assertTrue(is_a($this->Comments, 'CommentsController'));
	}

/**
 * Test admin_index action
 *
 * @return void
 */
	public function testAdminIndex() {
		$this->Comments->admin_index();
		$this->assertEquals(count($this->Comments->viewVars['comments']), 4);
		$this->assertEquals($this->Comments->viewVars['comments'][0]['Comment']['id'], 1);

		$this->Comments->admin_index('clean');
		$this->assertEquals(count($this->Comments->viewVars['comments']), 3);

		$this->Comments->admin_index(null);
		$this->assertEquals(count($this->Comments->viewVars['comments']), 4);
	}

/**
 * Test admin_process action
 *
 * @return void
 */
	public function _testAdminProcessDelete() {
		$this->Comments->request->data['Comment'] = array(
			'1' => 1,
			'2' => 0,
			'3' => 0,
			'action' => 'delete');
		$this->Comments->admin_process();
		$comment1 = $this->Comments->Comment->findById(1);
		$this->assertFalse($comment1);
		$comment2 = $this->Comments->Comment->findById(2);
		$this->assertIsA($comment2, 'Array');
	}

	public function testAdminProcessHam() {
		$this->Comments->request->data['Comment'] = array(
			'1' => 1,
			'2' => 0,
			'action' => 'ham');
		$this->Comments->admin_process();
		$comment1 = $this->Comments->Comment->findById(1);
		$this->assertEquals($comment1['Comment']['is_spam'], 'ham');
	}

	public function testAdminProcessSpam() {
		$this->Comments->request->data['Comment'] = array(
			'1' => 1,
			'2' => 0,
			'action' => 'spam');
		$this->Comments->admin_process();
		$comment1 = $this->Comments->Comment->findById(1);
		$this->assertEquals($comment1['Comment']['is_spam'], 'spammanual');
	}

	public function testAdminProcessApprove() {
		$this->Comments->request->data['Comment'] = array(
			'2' => 0,
			'3' => 1,
			'action' => 'approve');
		$this->Comments->admin_process();
		$comment = $this->Comments->Comment->findById(3);
		$this->assertEquals($comment['Comment']['approved'], 1);
	}

	public function testAdminProcessDisapprove() {
		$this->Comments->request->data['Comment'] = array(
			'1' => 1,
			'2' => 0,
			'action' => 'disapprove');
		$this->Comments->admin_process();
		$comment = $this->Comments->Comment->findById(1);
		$this->assertEquals($comment['Comment']['approved'], 0);
	}

/**
 * Test admin_spam action
 *
 * @return void
 */
	public function testAdminSpam() {
		$this->Comments->admin_spam('invalid-comment');
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Wrong comment id');
		$this->Comments->Session->delete('Message.flash.message');

		$Article = ClassRegistry::init('Article');
		$oldCount = array_shift(Set::extract($Article->read(array('Article.comments'), 1), '/Article/comments'));
		$this->Comments->admin_spam(1);
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Antispam system informed about spam message.');
		$commentFlag = $this->Comments->Comment->read(array('Comment.is_spam'), 1);
		$this->assertEquals($commentFlag['Comment']['is_spam'], 'spammanual');
		$newCount = array_shift(Set::extract($Article->read(array('Article.comments'), 1), '/Article/comments'));
		$this->assertEquals($newCount, $oldCount - 1);
		$this->Comments->Session->delete('Message.flash.message');
	}

/**
 * Test admin_ham action
 *
 * @return void
 */
	public function testAdminHam() {
		$this->Comments->admin_ham('invalid-comment');
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Wrong comment id');
		$this->Comments->Session->delete('Message.flash.message');

		$Article = ClassRegistry::init('Article');
		$oldCount = array_shift(Set::extract($Article->read(array('Article.comments'), 2), '/Article/comments'));
		$this->Comments->admin_ham(3);
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Antispam system informed about ham message.');
		$commentFlag = $this->Comments->Comment->read(array('Comment.is_spam'), 3);
		$this->assertEquals($commentFlag['Comment']['is_spam'], 'ham');
		$newCount = array_shift(Set::extract($Article->read(array('Article.comments'), 2), '/Article/comments'));
		$this->assertEquals($newCount, $oldCount + 1);
		$this->Comments->Session->delete('Message.flash.message');
	}

/**
 * Test admin_view action
 *
 * @return void
 */
	public function testAdminView() {
		$this->Comments->admin_view('invalid-comment');
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Invalid Comment.');
		$this->Comments->Session->delete('Message.flash.message');

		$this->Comments->admin_view(1);
		$this->assertEquals($this->Comments->viewVars['comment']['Comment']['id'], 1);
	}

/**
 * Test admin_delete action
 *
 * @return void
 */
	public function testAdminDelete() {
		$this->Comments->admin_delete('invalid-comment');
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Invalid id for Comment');
		$this->Comments->Session->delete('Message.flash.message');

		$Article = ClassRegistry::init('Article');
		$oldCount = array_shift(Set::extract($Article->read(array('Article.comments'), 1), '/Article/comments'));
		$this->Comments->admin_delete(1);
		$this->assertEquals($this->Comments->redirectUrl, array('action' => 'index'));
		$this->assertEquals($this->Comments->Session->read('Message.flash.message'), 'Comment deleted');
		$newCount = array_shift(Set::extract($Article->read(array('Article.comments'), 1), '/Article/comments'));
		$this->assertEquals($newCount, $oldCount - 1);
		$this->Comments->Session->delete('Message.flash.message');
	}

/**
 * Test requestForUser action
 *
 * @return void
 */
	public function testRequestForUser() {
		$this->Comments->requestForUser();
		$this->assertEquals($this->Comments->cakeErrorMethod, '404');

		$this->Comments->params['requested'] = array();
		$this->Comments->requestForUser();
		$ids = Set::extract($this->Comments->viewVars['comments'], '/Comment/id');
		$this->assertEquals($ids, array(1, 2));
		$this->assertEquals($this->Comments->renderedView, 'comment');

		$this->Comments->requestForUser(null, 1);
		$ids = Set::extract($this->Comments->viewVars['comments'], '/Comment/id');
		$this->assertEquals($ids, array(1));

		$this->Comments->requestForUser('47ea303a-3b2c-4251-b313-4816c0a800fa');
		$ids = Set::extract($this->Comments->viewVars['comments'], '/Comment/id');
		$this->assertEquals($ids, array(4));
		$this->assertEquals($this->Comments->viewVars['userId'], '47ea303a-3b2c-4251-b313-4816c0a800fa');

		$this->Comments->params['named']['model'] = 'Other';
		$this->Comments->requestForUser();
		$this->assertTrue(empty($this->Comments->viewVars['comments']));
	}

/**
 * (non-PHPdoc)
 * @see cake/tests/lib/CakeTestCase#endTest($method)
 */
	public function tearDown() {
		unset($this->Comments);
	}
}

