<?php
class Download {
  public $id;
  public $user_id;
  public $title;
  public $description;
  public $filename;
  public $filepath;
  public $filesize;
  public $filetype;
  public $created_at;
  public $is_published;

  public function __construct($id, $user_id, $title, $description, $filename, $filepath, $filesize = 0, $filetype = '', $is_published = 1, $created_at = null) {
    $this->id = (int)$id;
    $this->user_id = $user_id;
    $this->title = $title;
    $this->description = $description;
    $this->filename = $filename;
    $this->filepath = $filepath;
    $this->filesize = $filesize;
    $this->filetype = $filetype;
    $this->is_published = $is_published;
    $this->created_at = $created_at ? $created_at : date('Y-m-d H:i:s');
  }
}

class DownloadManager extends DBManager {
  public function __construct() {
    parent::__construct();
    $this->columns = array('id', 'user_id', 'title', 'description', 'filename', 'filepath', 'filesize', 'filetype', 'created_at', 'is_published');
    $this->tableName = 'downloads';
  }

  public function uploadFile($file, $userId, $title, $description = '') {
    $targetDir = ROOT_PATH . 'uploads/';
    if (!file_exists($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    $filename = $file['name'];
    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
    $safeFilename = uniqid() . '.' . $fileExt;
    $targetFile = $targetDir . $safeFilename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
      $filesize = filesize($targetFile);
      $download = new Download(
        0, $userId, $title, $description,
        $filename, 'uploads/' . $safeFilename,
        $filesize, $file['type']
      );
      return $this->create($download);
    }

    return false;
  }

  public function getAllDownloadsWithUserInfo() {
    return $this->db->query("
      SELECT d.*, u.first_name, u.last_name
      FROM downloads d
      JOIN user u ON d.user_id = u.id
      WHERE d.is_published = 1
      ORDER BY d.created_at DESC
    ");
  }

  public function getAllAdmin() {
    return $this->db->prepare(
      "SELECT d.*, u.first_name, u.last_name,
              COUNT(nd.news_id) AS linked_news_count
       FROM downloads d
       JOIN user u ON d.user_id = u.id
       LEFT JOIN news_downloads nd ON d.id = nd.download_id
       GROUP BY d.id
       ORDER BY d.created_at DESC"
    );
  }

  public function updateInfo($id, $title, $description) {
    $this->db->execute(
      "UPDATE downloads SET title = ?, description = ? WHERE id = ?",
      [trim($title), trim($description), (int)$id]
    );
  }
}
