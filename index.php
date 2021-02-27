<?php

use Core\Shortener;
use Core\UserAgent;

require dirname(__FILE__) . '/core/autoload.php';

$s = new Shortener();

if (!empty($_GET['c']))
{
  $code = htmlspecialchars(trim($_GET['c']));
  $code = rtrim(rtrim(rtrim(rtrim($code, '.'), ','), '('), ')');

  $link = $s->getActiveLink($code);

  if ($link)
  {
    try
    {
      if (!empty($_SERVER['HTTP_REFERER']))
      {
        $referer = htmlspecialchars(trim($_SERVER['HTTP_REFERER']));
        $referer_host = parse_url($referer, PHP_URL_HOST);

        // Useless, but you know, just in case.
        header('Referer: ' . $referer);
      }
      if (!empty($_SERVER['HTTP_USER_AGENT']))
      {
        $user_agent = htmlspecialchars(trim($_SERVER['HTTP_USER_AGENT']));
      }
      $ip_hash = sha1($_SERVER['REMOTE_ADDR'] . ($user_agent ?? null));

      $s->addView(
        $link['id'],
        $ip_hash ?? null,
        $referer_host ?? null,
        $referer ?? null,
        $user_agent ?? null
      );
    }
    catch (\Exception $e)
    {
      var_dump($e);
    }
    finally
    {
      http_response_code(301);
      header('Location: ' . htmlspecialchars_decode($link['url']));
      exit;
    }
  }
  else
  {
    http_response_code(404);
    $s->draw(404);
    exit;
  }
}

$link = $s->getActiveLink('default');
if ($link)
{
  header('Location: ' . $link['url']);
  exit;
}

$s->assign('time', time());
$s->draw('default');
