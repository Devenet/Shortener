<?php

namespace Core;

/**
 *  RainTPL
 *  -------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under the MIT license http://www.opensource.org/licenses/mit-license.php
 *
 *  @version 2.7.2
 */

class RainTPL
{
  static $tpl_dir = "themes/default/templates/";
  static $cache_dir = "themes/cache/";
  static $base_url = null;
  static $tpl_ext = "html";
  static $path_replace = false;
  static $path_replace_list = array( /*'a', 'img',*/ 'link', 'script', 'input');
  static $black_list = array('\$this', 'raintpl::', 'self::', '_SESSION', '_SERVER', '_ENV',  'eval', 'exec', 'unlink', 'rmdir');
  static $check_template_update = true;
  static $php_enabled = false;
  static $debug = false;
  static $root_dir = '';

  public $var = array();

  protected $tpl = array(), $cache = false, $cache_id = null;
  protected static $config_name_sum = array();

  const CACHE_EXPIRE_TIME = 3600;

  function assign($variable, $value = null)
  {
    if (is_array($variable))
      $this->var = $variable + $this->var;
    else
      $this->var[$variable] = $value;
  }

  function draw($tpl_name, $return_string = false)
  {

    try
    {
      $this->check_template($tpl_name);
    }
    catch (RainTpl_Exception $e)
    {
      $output = $this->printDebug($e);
      die($output);
    }

    if (!$this->cache && !$return_string)
    {
      extract($this->var);
      include $this->tpl['compiled_filename'];
      unset($this->tpl);
    }
    else
    {
      ob_start();
      extract($this->var);
      include $this->tpl['compiled_filename'];
      $raintpl_contents = ob_get_clean();

      if ($this->cache)
        file_put_contents($this->tpl['cache_filename'], "<?php if(!class_exists('raintpl')){exit;}?>" . $raintpl_contents);

      unset($this->tpl);

      if ($return_string) return $raintpl_contents;
      else echo $raintpl_contents;
    }
  }

  function cache($tpl_name, $expire_time = self::CACHE_EXPIRE_TIME, $cache_id = null)
  {
    $this->cache_id = $cache_id;

    if (!$this->check_template($tpl_name) && file_exists($this->tpl['cache_filename']) && (time() - filemtime($this->tpl['cache_filename']) < $expire_time))
    {
      return substr(file_get_contents($this->tpl['cache_filename']), 43);
    }
    else
    {
      if (file_exists($this->tpl['cache_filename']))
        unlink($this->tpl['cache_filename']);
      $this->cache = true;
    }
  }

  static function configure($setting, $value = null)
  {
    if (is_array($setting))
      foreach ($setting as $key => $value)
        self::configure($key, $value);
    else if (property_exists(__CLASS__, $setting))
    {
      self::$$setting = $value;
      self::$config_name_sum[$setting] = $value;
    }
  }

  protected function check_template($tpl_name)
  {
    if (!isset($this->tpl['checked']))
    {
      $tpl_basename = basename($tpl_name);
      $tpl_basedir = strpos($tpl_name, "/") ? dirname($tpl_name) . '/' : null;
      $this->tpl['template_directory'] = self::$tpl_dir . $tpl_basedir;
      $this->tpl['tpl_filename'] = self::$root_dir . $this->tpl['template_directory'] . $tpl_basename . '.' . self::$tpl_ext;
      $temp_compiled_filename = self::$root_dir . self::$cache_dir . $tpl_basename . "." . md5($this->tpl['template_directory'] . serialize(self::$config_name_sum));
      $this->tpl['compiled_filename'] = $temp_compiled_filename . '.rtpl.php';
      $this->tpl['cache_filename'] = $temp_compiled_filename . '.s_' . $this->cache_id . '.rtpl.php';
      $this->tpl['checked'] = true;

      if (self::$check_template_update && !file_exists($this->tpl['tpl_filename']) && !preg_match('/http/', $tpl_name))
      {
        $e = new RainTpl_NotFoundException('Template ' . $tpl_basename . ' not found!');
        throw $e->setTemplateFile($this->tpl['tpl_filename']);
      }

      if (preg_match('/http/', $tpl_name))
      {
        $this->compileFile('', '', $tpl_name, self::$root_dir . self::$cache_dir, $this->tpl['compiled_filename']);
        return true;
      }
      elseif (!file_exists($this->tpl['compiled_filename']) || (self::$check_template_update && filemtime($this->tpl['compiled_filename']) < filemtime($this->tpl['tpl_filename'])))
      {
        $this->compileFile($tpl_basename, $tpl_basedir, $this->tpl['tpl_filename'], self::$root_dir . self::$cache_dir, $this->tpl['compiled_filename']);
        return true;
      }
    }
  }

  protected function xml_reSubstitution($capture)
  {
    return "<?php echo '<?xml " . stripslashes($capture[1]) . " ?>'; ?>";
  }

  protected function compileFile($tpl_basename, $tpl_basedir, $tpl_filename, $cache_dir, $compiled_filename)
  {
    $this->tpl['source'] = $template_code = file_get_contents($tpl_filename);

    $template_code = preg_replace("/<\?xml(.*?)\?>/s", "##XML\\1XML##", $template_code);

    if (!self::$php_enabled)
      $template_code = str_replace(array("<?", "?>"), array("&lt;?", "?&gt;"), $template_code);

    $template_code = preg_replace_callback("/##XML(.*?)XML##/s", array($this, 'xml_reSubstitution'), $template_code);

    $template_compiled = "<?php if(!class_exists('Core\RainTPL')){exit;}?>" . $this->compileTemplate($template_code, $tpl_basedir);

    $template_compiled = str_replace("?>\n", "?>\n\n", $template_compiled);

    if (!is_dir($cache_dir))
      mkdir($cache_dir, 0755, true);

    if (!is_writable($cache_dir))
      throw new RainTpl_Exception('Cache directory ' . $cache_dir . 'doesn’t have write permission. Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to false. More details on https://feulf.github.io/raintpl');

    file_put_contents($compiled_filename, $template_compiled);
  }

  protected function compileTemplate($template_code, $tpl_basedir)
  {
    $tag_regexp = array(
      'loop'         => '(\{loop(?: name){0,1}="\${0,1}[^"]*"\})',
      'break'      => '(\{break\})',
      'loop_close'   => '(\{\/loop\})',
      'if'           => '(\{if(?: condition){0,1}="[^"]*"\})',
      'elseif'       => '(\{elseif(?: condition){0,1}="[^"]*"\})',
      'else'         => '(\{else\})',
      'if_close'     => '(\{\/if\})',
      'function'     => '(\{function="[^"]*"\})',
      'noparse'      => '(\{noparse\})',
      'noparse_close' => '(\{\/noparse\})',
      'ignore'       => '(\{ignore\}|\{\*)',
      'ignore_close'  => '(\{\/ignore\}|\*\})',
      'include'      => '(\{include="[^"]*"(?: cache="[^"]*")?\})',
      'template_info' => '(\{\$template_info\})',
      'function'    => '(\{function="(\w*?)(?:.*?)"\})'
    );

    $tag_regexp = "/" . join("|", $tag_regexp) . "/";

    $template_code = $this->path_replace($template_code, $tpl_basedir);
    $template_code = preg_split($tag_regexp, $template_code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    $compiled_code = $this->compileCode($template_code);

    return $compiled_code;
  }

  protected function compileCode($parsed_code)
  {
    if (!$parsed_code)
      return "";

    $compiled_code = $open_if = $comment_is_open = $ignore_is_open = null;
    $loop_level = 0;

    foreach ($parsed_code as $html)
    {
      if (!$comment_is_open && (strpos($html, '{/ignore}') !== FALSE || strpos($html, '*}') !== FALSE))
        $ignore_is_open = false;

      elseif ($ignore_is_open) {}

      elseif (strpos($html, '{/noparse}') !== FALSE)
        $comment_is_open = false;

      elseif ($comment_is_open)
        $compiled_code .= $html;

      elseif (strpos($html, '{ignore}') !== FALSE || strpos($html, '{*') !== FALSE)
        $ignore_is_open = true;

      elseif (strpos($html, '{noparse}') !== FALSE)
        $comment_is_open = true;

      elseif (preg_match('/\{include="([^"]*)"(?: cache="([^"]*)"){0,1}\}/', $html, $code))
      {
        if (preg_match("/http/", $code[1]))
        {
          $content = file_get_contents($code[1]);
          $compiled_code .= $content;
        }
        else
        {
          $include_var = $this->var_replace($code[1], $left_delimiter = null, $right_delimiter = null, $php_left_delimiter = '".', $php_right_delimiter = '."', $loop_level);

          $actual_folder = substr($this->tpl['template_directory'], strlen(self::$tpl_dir));

          $include_template = $actual_folder . $include_var;
          $include_template = $this->reduce_path($include_template);

          if (isset($code[2]))
          {
            $compiled_code .= '<?php $tpl = new ' . get_called_class() . ';' .
              'if( $cache = $tpl->cache( "' . $include_template . '" ) )' .
              '	echo $cache;' .
              'else{' .
              '$tpl->assign( $this->var );' .
              (!$loop_level ? null : '$tpl->assign( "key", $key' . $loop_level . ' ); $tpl->assign( "value", $value' . $loop_level . ' );') .
              '$tpl->draw( "' . $include_template . '" );' .
              '}' .
              '?>';
          }
          else
          {
            $compiled_code .= '<?php $tpl = new ' . get_called_class() . ';' .
              '$tpl->assign( $this->var );' .
              (!$loop_level ? null : '$tpl->assign( "key", $key' . $loop_level . ' ); $tpl->assign( "value", $value' . $loop_level . ' );') .
              '$tpl->draw( "' . $include_template . '" );' .
              '?>';
          }
        }
      }

      elseif (preg_match('/\{loop(?: name){0,1}="\${0,1}([^"]*)"\}/', $html, $code))
      {
        $loop_level++;

        $var = $this->var_replace('$' . $code[1], $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level - 1);

        $counter = "\$counter$loop_level";
        $key = "\$key$loop_level";
        $value = "\$value$loop_level";

        $compiled_code .=  "<?php $counter=-1; if( !is_null($var) && is_array($var) && sizeof($var) ) foreach( $var as $key => $value ){ $counter++; ?>";
      }

      elseif (strpos($html, '{break}') !== FALSE)
      {
        $compiled_code .=   '<?php break; ?>';
      }

      elseif (strpos($html, '{/loop}') !== FALSE)
      {
        $counter = "\$counter$loop_level";
        $loop_level--;
        $compiled_code .=  "<?php } ?>";
      }

      elseif (preg_match('/\{if(?: condition){0,1}="([^"]*)"\}/', $html, $code))
      {
        $open_if++;

        $tag = $code[0];
        $condition = $code[1];

        $this->function_check($tag);

        $parsed_condition = $this->var_replace($condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level);

        $compiled_code .=   "<?php if( $parsed_condition ){ ?>";
      }

      elseif (preg_match('/\{elseif(?: condition){0,1}="([^"]*)"\}/', $html, $code))
      {
        $tag = $code[0];
        $condition = $code[1];

        $parsed_condition = $this->var_replace($condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level);

        $compiled_code .=   "<?php }elseif( $parsed_condition ){ ?>";
      }

      elseif (strpos($html, '{else}') !== FALSE)
      {
        $compiled_code .=   '<?php }else{ ?>';
      }

      elseif (strpos($html, '{/if}') !== FALSE)
      {
        $open_if--;

        $compiled_code .=   '<?php } ?>';
      }

      elseif (preg_match('/\{function="(\w*)(.*?)"\}/', $html, $code))
      {
        $tag = $code[0];

        $function = $code[1];

        $this->function_check($tag);

        if (empty($code[2]))
          $parsed_function = $function . "()";
        else
          $parsed_function = $function . $this->var_replace($code[2], $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level);

        $compiled_code .=   "<?php echo $parsed_function; ?>";
      }

      elseif (strpos($html, '{$template_info}') !== FALSE)
      {
        $tag  = '{$template_info}';

        $compiled_code .=   '<?php echo "<pre>"; print_r( $this->var ); echo "</pre>"; ?>';
      }

      else
      {
        $html = $this->var_replace($html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true);
        $html = $this->const_replace($html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true);
        $compiled_code .= $this->func_replace($html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true);
      }
    }

    if ($open_if > 0)
    {
      $e = new RainTpl_SyntaxException('Error! You need to close an {if} tag in ' . $this->tpl['tpl_filename'] . ' template');
      throw $e->setTemplateFile($this->tpl['tpl_filename']);
    }
    return $compiled_code;
  }

  protected function reduce_path($path)
  {
    $path = str_replace("://", "@not_replace@", $path);
    $path = preg_replace("#(/+)#", "/", $path);
    $path = preg_replace("#(/\./+)#", "/", $path);
    $path = str_replace("@not_replace@", "://", $path);

    while (preg_match('#\.\./#', $path))
    {
      $path = preg_replace('#\w+/\.\./#', '', $path);
    }
    return $path;
  }

  protected function rewrite_url($url, $tag, $path)
  {
    if (!in_array($tag, self::$path_replace_list))
    {
      return $url;
    }

    $protocol = 'http|https|ftp|file|apt|magnet';
    if ($tag == 'a')
    {
      $protocol .= '|mailto|javascript';
    }

    $no_change = "/(^($protocol)\:)|(#$)/i";
    if (preg_match($no_change, $url))
    {
      return rtrim($url, '#');
    }

    $base_only = '/^\//';
    if ($tag == 'a' or $tag == 'form')
    {
      $base_only = '//';
    }
    if (preg_match($base_only, $url))
    {
      return rtrim(self::$base_url, '/') . '/' . ltrim($url, '/');
    }

    return $path . $url;
  }

  protected function single_path_replace($matches)
  {
    $tag  = $matches[1];
    $_    = $matches[2];
    $attr = $matches[3];
    $url  = $matches[4];
    $new_url = $this->rewrite_url($url, $tag, $this->path);

    return "<$tag$_$attr=\"$new_url\"";
  }

  protected function path_replace($html, $tpl_basedir)
  {
    if (self::$path_replace)
    {
      $tpl_dir = self::$base_url . self::$tpl_dir . $tpl_basedir;

      $this->path = $this->reduce_path($tpl_dir);

      $url = '(?:(?:\\{.*?\\})?[^{}]*?)*?';

      $exp = array();

      $tags = array_intersect(array("link", "a"), self::$path_replace_list);
      $exp[] = '/<(' . join('|', $tags) . ')(.*?)(href)="(' . $url . ')"/i';

      $tags = array_intersect(array("img", "script", "input"), self::$path_replace_list);
      $exp[] = '/<(' . join('|', $tags) . ')(.*?)(src)="(' . $url . ')"/i';

      $tags = array_intersect(array("form"), self::$path_replace_list);
      $exp[] = '/<(' . join('|', $tags) . ')(.*?)(action)="(' . $url . ')"/i';

      return preg_replace_callback($exp, 'self::single_path_replace', $html);
    }
    else
      return $html;
  }

  function const_replace($html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null)
  {
    return preg_replace('/\{\#(\w+)\#{0,1}\}/', $php_left_delimiter . ($echo ? " echo " : null) . '\\1' . $php_right_delimiter, $html);
  }

  function func_replace($html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null)
  {
    preg_match_all('/' . '\{\#{0,1}(\"{0,1}.*?\"{0,1})(\|\w.*?)\#{0,1}\}' . '/', $html, $matches);

    for ($i = 0, $n = count($matches[0]); $i < $n; $i++)
    {
      $tag = $matches[0][$i];

      $var = $matches[1][$i];
      $extra_var = $matches[2][$i];

      $this->function_check($tag);

      $extra_var = $this->var_replace($extra_var, null, null, null, null, $loop_level);
      $is_init_variable = preg_match("/^(\s*?)\=[^=](.*?)$/", $extra_var);
      $function_var = ($extra_var and $extra_var[0] == '|') ? substr($extra_var, 1) : null;
      $temp = preg_split("/\.|\[|\-\>/", $var);
      $var_name = $temp[0];
      $variable_path = substr($var, strlen($var_name));
      $variable_path = str_replace('[', '["', $variable_path);
      $variable_path = str_replace(']', '"]', $variable_path);
      $variable_path = preg_replace('/\.\$(\w+)/', '["$\\1"]', $variable_path);
      $variable_path = preg_replace('/\.(\w+)/', '["\\1"]', $variable_path);

      if ($function_var)
      {
        $function_var = str_replace("::", "@double_dot@", $function_var);

        if ($dot_position = strpos($function_var, ":"))
        {
          $function = substr($function_var, 0, $dot_position);
          $params = substr($function_var, $dot_position + 1);
        }
        else
        {
          $function = str_replace("@double_dot@", "::", $function_var);
          $params = null;
        }

        $function = str_replace("@double_dot@", "::", $function);
        $params = str_replace("@double_dot@", "::", $params);
      }
      else
        $function = $params = null;

      $php_var = $var_name . $variable_path;

      if (isset($function))
      {
        if ($php_var)
          $php_var = $php_left_delimiter . (!$is_init_variable && $echo ? 'echo ' : null) . ($params ? "( $function( $php_var, $params ) )" : "$function( $php_var )") . $php_right_delimiter;
        else
          $php_var = $php_left_delimiter . (!$is_init_variable && $echo ? 'echo ' : null) . ($params ? "( $function( $params ) )" : "$function()") . $php_right_delimiter;
      }
      else
        $php_var = $php_left_delimiter . (!$is_init_variable && $echo ? 'echo ' : null) . $php_var . $extra_var . $php_right_delimiter;

      $html = str_replace($tag, $php_var, $html);
    }

    return $html;
  }



  function var_replace($html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null)
  {
    if (preg_match_all('/' . $tag_left_delimiter . '\$(\w+(?:\.\${0,1}[A-Za-z0-9_]+)*(?:(?:\[\${0,1}[A-Za-z0-9_]+\])|(?:\-\>\${0,1}[A-Za-z0-9_]+))*)(.*?)' . $tag_right_delimiter . '/', $html, $matches))
    {
      for ($parsed = array(), $i = 0, $n = count($matches[0]); $i < $n; $i++)
        $parsed[$matches[0][$i]] = array('var' => $matches[1][$i], 'extra_var' => $matches[2][$i]);

      foreach ($parsed as $tag => $array)
      {
        $var = $array['var'];
        $extra_var = $array['extra_var'];

        $this->function_check($tag);

        $extra_var = $this->var_replace($extra_var, null, null, null, null, $loop_level);

        $is_init_variable = preg_match("/^[a-z_A-Z\.\[\](\-\>)]*=[^=]*$/", $extra_var);

        $function_var = ($extra_var and $extra_var[0] == '|') ? substr($extra_var, 1) : null;

        $temp = preg_split("/\.|\[|\-\>/", $var);

        $var_name = $temp[0];

        $variable_path = substr($var, strlen($var_name));

        $variable_path = str_replace('[', '["', $variable_path);
        $variable_path = str_replace(']', '"]', $variable_path);

        $variable_path = preg_replace('/\.(\${0,1}\w+)/', '["\\1"]', $variable_path);

        if ($is_init_variable)
          $extra_var = "=\$this->var['{$var_name}']{$variable_path}" . $extra_var;

        if ($function_var)
        {
          $function_var = str_replace("::", "@double_dot@", $function_var);

          if ($dot_position = strpos($function_var, ":"))
          {
            $function = substr($function_var, 0, $dot_position);
            $params = substr($function_var, $dot_position + 1);
          }
          else
          {
            $function = str_replace("@double_dot@", "::", $function_var);
            $params = null;
          }

          $function = str_replace("@double_dot@", "::", $function);
          $params = str_replace("@double_dot@", "::", $params);
        }
        else
          $function = $params = null;

        if ($loop_level)
        {
          if ($var_name == 'key')
            $php_var = '$key' . $loop_level;
          elseif ($var_name == 'value')
            $php_var = '$value' . $loop_level . $variable_path;
          elseif ($var_name == 'counter')
            $php_var = '$counter' . $loop_level;
          else
            $php_var = '$' . $var_name . $variable_path;
        }
        else
          $php_var = '$' . $var_name . $variable_path;

        if (isset($function))
          $php_var = $php_left_delimiter . (!$is_init_variable && $echo ? 'echo ' : null) . ($params ? "( $function( $php_var, $params ) )" : "$function( $php_var )") . $php_right_delimiter;
        else
          $php_var = $php_left_delimiter . (!$is_init_variable && $echo ? 'echo ' : null) . $php_var . $extra_var . $php_right_delimiter;

        $html = str_replace($tag, $php_var, $html);
      }
    }

    return $html;
  }

  protected function function_check($code)
  {
    $preg = '#(\W|\s)' . implode('(\W|\s)|(\W|\s)', self::$black_list) . '(\W|\s)#';

    if (count(self::$black_list) && preg_match($preg, $code, $match))
    {
      $line = 0;
      $rows = explode("\n", $this->tpl['source']);
      while (!strpos($rows[$line], $code))
        $line++;

        $e = new RainTpl_SyntaxException('Unallowed syntax in ' . $this->tpl['tpl_filename'] . ' template');
      throw $e->setTemplateFile($this->tpl['tpl_filename'])
        ->setTag($code)
        ->setTemplateLine($line);
    }
  }

  protected function printDebug(RainTpl_Exception $e)
  {
    if (!self::$debug)
    {
      throw $e;
    }
    $output = sprintf(
      '<h2>Exception: %s</h2><h3>%s</h3><p>template: %s</p>',
      get_class($e),
      $e->getMessage(),
      $e->getTemplateFile()
    );
    if ($e instanceof RainTpl_SyntaxException)
    {
      if (null != $e->getTemplateLine())
      {
        $output .= '<p>line: ' . $e->getTemplateLine() . '</p>';
      }
      if (null != $e->getTag())
      {
        $output .= '<p>in tag: ' . htmlspecialchars($e->getTag()) . '</p>';
      }
      if (null != $e->getTemplateLine() && null != $e->getTag())
      {
        $rows = explode("\n",  htmlspecialchars($this->tpl['source']));
        $rows[$e->getTemplateLine()] = '<font color=red>' . $rows[$e->getTemplateLine()] . '</font>';
        $output .= '<h3>template code</h3>' . implode('<br />', $rows) . '</pre>';
      }
    }
    $output .= sprintf(
      '<h3>trace</h3><p>In %s on line %d</p><pre>%s</pre>',
      $e->getFile(),
      $e->getLine(),
      nl2br(htmlspecialchars($e->getTraceAsString()))
    );
    return $output;
  }
}

class RainTpl_Exception extends \Exception
{
  protected $templateFile = '';

  public function getTemplateFile()
  {
    return $this->templateFile;
  }

  public function setTemplateFile($templateFile)
  {
    $this->templateFile = (string) $templateFile;
    return $this;
  }
}

class RainTpl_NotFoundException extends RainTpl_Exception {}

class RainTpl_SyntaxException extends RainTpl_Exception
{
  protected $templateLine = null;
  protected $tag = null;

  public function getTemplateLine()
  {
    return $this->templateLine;
  }

  public function setTemplateLine($templateLine)
  {
    $this->templateLine = (int) $templateLine;
    return $this;
  }

  public function getTag()
  {
    return $this->tag;
  }

  public function setTag($tag)
  {
    $this->tag = (string) $tag;
    return $this;
  }
}