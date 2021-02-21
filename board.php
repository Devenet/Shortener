<?php

use Core\Shortener;
use Core\Generator;

require dirname(__FILE__).'/core/autoload.php';

$s = new Shortener();

if (isset($_GET['new']) && !empty($_POST) && !empty($_POST['url']))
{
  /* Inspired by
    → https://github.com/mathiasbynens/php-url-shortener/blob/master/shorten.php
    → https://www.codexworld.com/php-url-shortener-library-create-short-url/
  */
  
  $url = htmlspecialchars(trim($_POST['url']));
  $code = htmlspecialchars(trim($_POST['code'] ?? null));
  $code_easy = isset($_POST['easy']) && $_POST['easy'] == "on";
  $chars_lower = isset($_POST['chars']) && isset($_POST['chars']['lower']) && $_POST['chars']['lower'] == "on";
  $chars_upper = isset($_POST['chars']) && isset($_POST['chars']['upper']) && $_POST['chars']['upper'] == "on";
  $chars_digits = isset($_POST['chars']) && isset($_POST['chars']['digits']) && $_POST['chars']['digits'] == "on";
  $chars_symbols = isset($_POST['chars']) && isset($_POST['chars']['symbols']) && $_POST['chars']['symbols'] == "on";
  $code_length = $_POST['length'] + 0;
  $comment = htmlspecialchars(trim($_POST['comment']));
  $disable = isset($_POST['disable']) && $_POST['disable'] == "on";
  
  $scheme = parse_url($url, PHP_URL_SCHEME);
  $host = parse_url($url, PHP_URL_HOST);
  $code_chars = [];
  if ($chars_lower) { $code_chars[] = Generator::CHARS_LOWER; }
  if ($chars_upper) { $code_chars[] = Generator::CHARS_UPPER; }
  if ($chars_digits) { $code_chars[] = Generator::CHARS_DIGITS; }
  if ($chars_symbols) { $code_chars[] = Generator::CHARS_SYMBOLS; }
  
  if (empty($scheme) || !in_array(strtolower($scheme), ['http', 'https']))
  {
    $errors['url'] = 'URL must start with <code>http</code> or <code>https</code>.';
  }
  else if (in_array($host, ['localhost', '127.0.0.1', 'about:blank']))
  {
    $errors['url'] = 'URL can’t be localhost.';
  }
  else if (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
  {
    $errors['url'] = 'URL can’t be a private or reserved IP address.';
  }
  else if (filter_var(htmlentities($url), FILTER_VALIDATE_URL) === false)
  {
    $errors['url'] = 'URL is <b>not</b> considered valid. Check your input.';
  }
  else if (Shortener::StartsWith($host, $s->getDomain()) && !isset($_POST['force_selfdomain']))
  {
    $errors['url'] = 'URL is already a shorten URL.';
  }
  else if (strlen($url) > 500)
  {
    $errors['url'] = 'Holy crap. URL is too long to be stored in database (maximum of 500 characters).';
  }
  
  $links = $s->getLinksFromUrl($url);
  if (count($links) > 0 && !isset($_POST['force_url']))
  {
    $links_code = implode(', ', array_map(function($link) { global $s; return '<a href="board.php?c='.$link['code'].'">'.$s->getDomain().'/'.$link['code'].'</a>'; }, $links));
    $errors['url'] = 'URL is already shorten with '.$links_code.'.';
  }
  
  if (empty($code))
  {
    if (empty($code_length) || $code_length < 3 || $code_length > 50)
    {
      $errors['length'] = 'Length must be between 3 and 50 charcaters.';
    }
    
    if (count($code_chars) == 0)
    {
      $errors['chars'] = 'At least one type of characters must be selected.';
    }
  }
  else
  {
    if (Shortener::EndsWith($code, '.') || Shortener::EndsWith($code, ',') || Shortener::EndsWith($code, '(') || Shortener::EndsWith($code, ')'))
    {
      $errors['code'] = 'Custom alias can not end with <code>.</code>, <code>,</code>, <code>(</code> or <code>)</code>.';
    }
    
    $link = $s->getLink($code);
    if ($link)
    {
      $errors['code'] = 'This alias is already used. Choose another.';
    }
  }

  if (!empty($_POST['comment']) && strlen($_POST['comment']) > 255)
  {
    $errors['comment'] = 'The comment must be less than 255 characters.';
  }
  
  
  if (empty($errors))
  {
    try
    {
      $code = $s->addLink($code, $url, $disable, $comment, ['length' => $code_length, 'easyToRead' => $code_easy, 'chars' => array_sum($code_chars)]);
      
      header('Location: board.php?c='.$code);
      exit;
    }
    catch (\Exception $e)
    {
      $errors['exception'] = $e->getMessage();
    }
  }

  $s->assign('errors', $errors);
  $s->assign('values', array(
    'url' => $url,
    'code' => $code,
    'easy' => $code_easy,
    'chars_lower' => $chars_lower,
    'chars_upper' => $chars_upper,
    'chars_digits' => $chars_digits,
    'chars_symbols' => $chars_symbols,
    'length' => $code_length,
    'comment' => $comment,
    'disable' => $disable
  ));
}
else if (!empty($_GET['c']))
{
  $code = htmlspecialchars(trim($_GET['c']));
  $link = $s->getLink($code);
  
  if ($link)
  {
    if (!empty($_POST))
    {
      if (!empty($_POST['delete']) && $_POST['delete'] == md5($link['code']))
      {
        $s->deleteLink($link['id']);
        header('Location: board.php');
        exit;
      }

      $comment = htmlspecialchars(trim($_POST['comment']));
      $disable = isset($_POST['disable']) && $_POST['disable'] == "on";

      if (!empty($_POST['comment']) && strlen($_POST['comment']) > 255)
      {
        $errors['comment'] = 'The comment must be less than 255 characters.';
      }

      if (empty($errors))
      {
        $s->updateLink($link['id'], $disable, $comment);
        header('Location: board.php?c='.$link['code'].'&updated');
        exit;	
      }

      $s->assign('errors', $errors);
      $s->assign('values', array(
        'comment' => $comment,
        'disable' => $disable
      ));
    }

    $views_count = $s->countViews($link['id']);
    $last_page = Shortener::getLastPage($views_count['total']);
    
    $page = 0;
    if (!empty($_GET['page']) && is_int($_GET['page']+0) && $_GET['page'] <= $last_page)
      $page = $_GET['page']+0;
    
    $views = $s->getViews($page, $link['id']);
    
    $s->assign('link', $link);
    $s->assign('views_count', $views_count['total']);
    $s->assign('views_unique_count', $views_count['unique']);
    $s->assign('views', $views);
    $s->assign('pagination_current', $page);
    $s->assign('pagination_last', $last_page);
    $s->assign('pagination', Shortener::getSiblingPages($page, $last_page));
    $s->assign('updated', isset($_GET['updated']));
    $s->draw('board-detail');
    exit;
  }
  
  http_response_code(404);
  $s->assign('display_menu', true);
  $s->draw(404);
  exit;
}

$s->assign('newlink', isset($_GET['new']));
  
$links_count = $s->countLinks();
$last_page = Shortener::getLastPage($links_count);

$page = 0;
if (!empty($_GET['page']) && is_int($_GET['page']+0) && $_GET['page'] <= $last_page)
  $page = $_GET['page']+0;

$links = $s->getLinks($page);

$s->assign('links_count', $links_count);
$s->assign('links', $links);
$s->assign('pagination_current', $page);
$s->assign('pagination_last', $last_page);
$s->assign('pagination', Shortener::getSiblingPages($page, $last_page));
$s->draw('board-list');
exit;
