<?php

namespace Core;

use PDO;

class Db
{
  private static $instance;
  private static $access = 0;

  static public function Instance()
  {
    if (!isset(self::$instance))
    {
      require dirname(__FILE__) . '/config.php';

      $dsn = $config['driver'] ?? null;
      switch ($dsn)
      {
        case 'mysql':
          // https://www.php.net/manual/fr/ref.pdo-mysql.connection.php
          $dsn .= ':host='.$config['host'].';port='.($config['port'] ?? 3306).';dbname='.$config['database'];
          $query_utf8 = true;
          break;

        case 'sqlite':
          // https://www.php.net/manual/fr/ref.pdo-sqlite.connection.php
          $dsn .= ':'.$config['database'];
          break;

        default:
          echo 'Database driver ' . $config['driver'] . ' not supported.';
          exit;
      }

      self::$instance = new PDO($dsn, $config['user'] ?? null, $config['password'] ?? null);
      self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      
      if (isset($query_utf8)) 
        self::$instance->query('SET NAMES UTF8');
    }
    self::$access++;
    return self::$instance;
  }

  static public function Access()
  {
    return self::$access;
  }
}
