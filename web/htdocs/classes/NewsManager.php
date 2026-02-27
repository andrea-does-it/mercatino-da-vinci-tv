<?php
class News {
  public $id;
  public $user_id;
  public $title;
  public $content;
  public $created_at;
  public $updated_at;
  public $is_published;

  public function __construct($id, $user_id, $title, $content, $is_published = 1, $created_at = null, $updated_at = null) {
    $this->id = (int)$id;
    $this->user_id = $user_id;
    $this->title = $title;
    $this->content = $content;
    $this->is_published = $is_published;
    $this->created_at = $created_at ? $created_at : date('Y-m-d H:i:s');
    $this->updated_at = $updated_at ? $updated_at : date('Y-m-d H:i:s');
  }
}

class NewsManager extends DBManager {
  public function __construct() {
    parent::__construct();
    $this->columns = array('id', 'user_id', 'title', 'content', 'created_at', 'updated_at', 'is_published');
    $this->tableName = 'news';
  }

  public function getRecentNews($limit = 10) {
    return $this->db->prepare(
      "SELECT n.*, u.first_name, u.last_name
       FROM news n
       JOIN user u ON n.user_id = u.id
       WHERE n.is_published = 1
       ORDER BY n.created_at DESC
       LIMIT ?",
      [(int)$limit]
    );
  }

  public function getAdminAll() {
    return $this->db->prepare(
      "SELECT n.*, u.first_name, u.last_name
       FROM news n
       JOIN user u ON n.user_id = u.id
       ORDER BY n.created_at DESC"
    );
  }

  public function togglePublish($id) {
    $this->db->execute(
      "UPDATE news SET is_published = 1 - is_published WHERE id = ?",
      [(int)$id]
    );
  }

  // togglePin() will be enabled after running the DB migration:
  // ALTER TABLE news ADD COLUMN is_pinned TINYINT(1) DEFAULT 0;
  public function togglePin($id) {
    $this->db->execute(
      "UPDATE news SET is_pinned = 1 - is_pinned WHERE id = ?",
      [(int)$id]
    );
  }
}
