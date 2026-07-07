<?php
class EmailTemplate {

  public $id;
  public $name;
  public $subject;
  public $body;

  public function __construct($id, $name, $subject, $body) {
    $this->id = (int)$id;
    $this->name = $name;
    $this->subject = $subject;
    $this->body = $body;
  }
}

class EmailTemplateManager extends DBManager {

  public function __construct() {
    parent::__construct();
    $this->columns = array('id', 'name', 'subject', 'body');
    $this->tableName = 'email_template';
  }

  public function getAllOrdered() {
    $rows = $this->db->prepare("
      SELECT id, name, subject, body, updated_at
      FROM email_template
      ORDER BY name
    ");
    $templates = array();
    foreach ($rows as $row) {
      $templates[] = (object)$row;
    }
    return $templates;
  }

  public function save($id, $name, $subject, $body) {
    $data = array('name' => $name, 'subject' => $subject, 'body' => $body);
    if ((int)$id > 0) {
      return $this->update($data, (int)$id);
    }
    return $this->create($data);
  }
}
