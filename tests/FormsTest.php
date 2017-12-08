<?php

use PHPUnit\Framework\TestCase;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HTML_Forms\Form;
use HTML_Forms\Forms;

class FormsTest extends TestCase {

	protected function setUp() {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown() {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function getNewInstance() {
		return new Forms( '', array() );
	}

	public function test_validate_form() {
		$instance = $this->getNewInstance();
		$form = new Form(1);

		// honeypot field missing
		$data = array();
		self::assertEquals('spam', $instance->validate_form($form, $data));

		// more fields than in form
		$form->markup = '';
		$data = array( '_hf_h1' => '', 'foo' => 'bar', 'bar' => 'foo' );
		self::assertEquals('spam', $instance->validate_form($form, $data));

		// required field missing
		$form->settings['required_fields'] = 'EMAIL';
		$form->markup = '<input type="text" name="EMAIL" />';
		$data = array( '_hf_h1' => '' );
		self::assertEquals('required_field_missing', $instance->validate_form($form, $data));

		// invalid email address
		Functions\when('is_email')->justReturn(false);
		$form->settings['email_fields'] = 'EMAIL';
		$form->settings['required_fields'] = 'EMAIL';
		$form->markup = '<input type="email" name="EMAIL" />';
		$data = array( '_hf_h1' => '', 'EMAIL' => 'invalid@email' );
		self::assertEquals('invalid_email', $instance->validate_form($form, $data));

		// all good
		Functions\when('is_email')->justReturn(true);
		$form->settings['email_fields'] = 'EMAIL';
		$form->settings['required_fields'] = 'EMAIL';
		$form->markup = '<input type="email" name="EMAIL" />';
		$data = array( '_hf_h1' => '', 'EMAIL' => 'valid@email.com' );
		self::assertEquals('', $instance->validate_form($form, $data));
	}
}
