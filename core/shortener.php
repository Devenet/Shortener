<?php

/**
 *  Developed by Nicolas Devenet <nicolas[at]devenet.info>
 *  Code hosted on https://github.com/Devenet/Shortener
 */

namespace Core;

use Core\Db;
use Core\Generator;
use Core\RainTPL;
use PDO;

class Shortener
{
  public const VERSION = '1.1.0';
  public const PAGINATION = 15;

  private $template;
  private $domain;
  private $domain_scheme;
  private $default_length;

  function __construct()
  {
    require dirname(__FILE__) . '/config.php';

    $theme = $config['theme'] ?? null;
    if (!empty($theme))
    {
      RainTPL::$tpl_dir = 'themes/' . $theme . '/';
    }

    $this->default_length = $config['default_code_length'] ?? 6;
    $this->domain = $config['domain'] ?? $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $this->domain = self::RemoveTrailingSlash($this->domain);
    $this->domain_scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';

    $this->template = new RainTPL();
    $this->assign('domain', $this->domain);
    $this->assign('domain_scheme', $this->domain_scheme);
    $this->assign('default_length', $this->default_length);
  }

  public function getDomain()
  {
    return $this->domain;
  }

  public function addLink($code, $url, $disable, $comment, $generator_settings)
  {
    if (empty($code))
    {
      $try = 1;
      do
      {
        $code = Generator::RandomCode($generator_settings['length'], $generator_settings['easyToRead'], $generator_settings['chars']);
        $link = $this->getLink($code);
        if (!$link)
          break;

        if ($try == 3)
          throw new \Exception('Unable to generate a unused random code after 3 tries.');
      } while ($try++ < 3);
    }

    $query = Db::Instance()->prepare('insert into shtnr_link(code, url, disable, comment) values(:code, :url, :disable, :comment)');
    $query->execute(array(
      'code' => $code,
      'url' => $url,
      'disable' => +$disable,
      'comment' => $comment,
    ));
    $query->closeCursor();

    return $code;
  }
  public function updateLink($id, $disable, $comment)
  {
    $query = Db::Instance()->prepare('update shtnr_link set disable = :disable, comment = :comment where id = :id');
    $query->execute(array(
      'id' => $id,
      'disable' => +$disable,
      'comment' => $comment,
    ));
    $query->closeCursor();

    return;
  }
  public function countLinks()
  {
    $query = Db::Instance()->query('select count(id) as total from shtnr_link');
    $data = $query->fetch();
    $query->closeCursor();

    return $data['total'];
  }
  public function getLink($code)
  {
    if (empty($code)) return null;

    $query = Db::Instance()->prepare('select id, created, code, url, disable, comment from shtnr_link where code = ?');
    $query->execute(array($code));
    $data = $query->fetch();
    $query->closeCursor();

    return $data;
  }
  public function getActiveLink($code)
  {
    if (empty($code)) return null;

    $query = Db::Instance()->prepare('select id, url from shtnr_link where code = ? and disable = 0');
    $query->execute(array($code));
    $data = $query->fetch();
    $query->closeCursor();

    return $data;
  }
  public function getLinks($page = 0)
  {
    $query = Db::Instance()->prepare('select l.id, l.created, l.code, l.url, l.disable, l.comment, count(v.id) as views
      from shtnr_link l left outer join shtnr_view v on l.id = v.link_id
      group by l.id order by l.created desc
      limit :offset, :pagination');
    $query->bindValue('offset', $page * self::PAGINATION, PDO::PARAM_INT);
    $query->bindValue('pagination', self::PAGINATION, PDO::PARAM_INT);
    $query->execute();

    $results = [];
    while ($data = $query->fetch())
      $results[] = $data;
    $query->closeCursor();

    return $results;
  }
  public function getLinksFromUrl($url)
  {
    if (empty($url)) return array();

    $query = Db::Instance()->prepare('select id, code from shtnr_link where url = ?');
    $query->execute(array($url));
    $data = $query->fetchAll();
    $query->closeCursor();

    return $data;
  }
  public function deleteLink($id)
  {
    if (empty($id)) return;

    $query = Db::Instance()->prepare('delete from shtnr_link where id = ?');
    $query->execute(array($id));
    $query->closeCursor();

    return;
  }

  public function addView($link_id, $ip_hash = null, $referer_host = null, $referer = null, $ua = null)
  {
    if (empty($link_id)) return;

    $query = Db::Instance()->prepare('insert into shtnr_view(link_id, ip_hash, referer_host, referer, user_agent) values(:id, :ip, :host, :referer, :ua)');
    $query->execute(array(
      'id' => $link_id,
      'ip' => substr($ip_hash, 0, 32),
      'host' => mb_substr($referer_host, 0, 100),
      'referer' => mb_substr($referer, 0, 300),
      'ua' => mb_substr($ua, 0, 200)
    ));
    $query->closeCursor();

    return;
  }
  public function countViews($link_id)
  {
    if (empty($link_id)) return null;

    // No CTE supported in MySQL < 8.0 although it is OK for MariaDB and SQLite…
    $query = Db::Instance()->prepare('select count(c.counts) as unique_counts, coalesce(sum(c.counts), 0) as counts
      from (select count(id) as counts from shtnr_view where link_id = ? group by ip_hash) c');
    $query->execute(array($link_id));
    $data = $query->fetch();
    $query->closeCursor();

    return array('total' => $data['counts'], 'unique' => $data['unique_counts']);
  }
  public function getViews($page = 0, $link_id)
  {
    if (empty($link_id)) return null;

    $query = Db::Instance()->prepare('select id, created, ip_hash, referer_host, referer, user_agent
      from shtnr_view where link_id = :link_id order by created desc
      limit :offset, :pagination');
    $query->bindValue('link_id', $link_id);
    $query->bindValue('offset', $page * self::PAGINATION, PDO::PARAM_INT);
    $query->bindValue('pagination', self::PAGINATION, PDO::PARAM_INT);
    $query->execute();

    $results = [];
    while ($data = $query->fetch())
      $results[] = $data;
    $query->closeCursor();

    return $results;
  }

  public static function getLastPage($count)
  {
    return ceil($count / self::PAGINATION);
  }
  public static function getSiblingPages($current, $max)
  {
    $siblings = 2;
    return range(max(0, $current - $siblings), min(max(0, $max - 1), $current + $siblings), 1);
  }

  public function assign($name, $value)
  {
    $this->template->assign($name, $value);
  }
  public function draw($template)
  {
    $this->template->assign('version', self::VERSION);
    $this->template->assign('db_access', Db::Access());
    $this->template->draw($template);
  }

  public static function StartsWith($content, $search)
  {
    return strcasecmp(substr($content, 0, strlen($search)), $search) === 0;
  }
  public static function EndsWith($content, $search)
  {
    return strcasecmp(substr($content, strlen($content) - strlen($search)), $search) === 0;
  }

  private static function RemoveTrailingSlash($content)
  {
    if (self::EndsWith($content, '/'))
      return substr($content, 0, -1);
    
    return $content;
  }
}
