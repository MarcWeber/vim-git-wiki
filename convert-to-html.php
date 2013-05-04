<?php
// PHP 5.3

/* global vars */

{ // config

  $target_directory = '../wiki'; // no spaces!

  $pages = array();
  $reference_pages = array();

  chdir('vim-online-wiki-source');
  $wiki_files = 
    array_merge(
      glob('*'),
      glob('*/*')
    );

  // these functions defines the design:
  $to_html = array(
      'page' => function($wiki_file_rel_path, $body){
        // TODO: be smarter, take first = .. = code from file
        $title = str_replace('/',' ',str_replace('.wiki', '', $wiki_file_rel_path));
        return sprintf('
<!doctype html>
<html>
<head>
  <title>%s</title>
    <meta name="robots" content="index,nofollow" />
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
%s
</body>
</html>
  ', $title, $body);
      },
      'code_block' => function($text){
        return "<pre class=\"code\">".$text."</pre>";
      },
      'wikilink' =>  function($wiki_path, $wiki_file_rel_path){
        $parts = pathinfo($wiki_file_rel_path);

        $href = $wiki_file_rel_path.'.html';
        $label = basename($wiki_path);
        $alt = $wiki_path;
        return sprintf('<a href="%s" alt="%s">%s</a>', quote($href), quote($alt), quote($label));
      }
  );

}

{ // LIB (helper functions)

  function quote($s){ return htmlentities($s); }

  function get_absolute_path($path) {
          $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
          $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
          $absolutes = array();
          foreach ($parts as $part) {
              if ('.' == $part) continue;
              if ('..' == $part) {
                  array_pop($absolutes);
              } else {
                  $absolutes[] = $part;
              }
          }
          return implode(DIRECTORY_SEPARATOR, $absolutes);
  }

  function process_lists($s){
    // I'm not that happy about this code, but it'll work
    // on 1) start numbered list (item)
    // on *) start bullet list (item)
    // on "  " continue list
    // else stop list or keep line

    $result = array();
    $state = '';
    $p_end = null;

    $a = function($s) use(&$result){ $result[] = $s; };
    $end_list = function() use(&$result, &$state, &$p_end){ 
      $p_end();
      $result[] = "</ul>\n"; $state ='';
    };

    # start end paragraphs
    $in_p = false;
    $p_start = function() use(&$in_p, &$a){
      if (!$in_p){
        $a("\n<p>");
        $in_p = true;
      }
    };
    $p_end = function() use(&$in_p, &$a){
      if ($in_p) {
        $a('</p>');
        $in_p = false;
      }
    };

    foreach (explode("\n", $s) as $line) {
      if (preg_match('/^(=+) (.*)$/', $line, $m)){
        $p_end();
        $l = strlen($m[1]);
        $a(sprintf("\n<h%d>%s</h%d>", $l, quote(preg_replace('/=*$/', '', $m[2])), $l));
      } else if (in_array($state, array( "in_numbered_list", "in_bullet_list"))){
        if ($state == "in_bullet_list" && preg_match('/^\*(.*)/', $line, $m)){
          $a("<li>".$m[1]);
        } else if ($state == "in_numbered_list" && preg_match('/^[0-9]*\)(.*)/', $line,  $m)){
          $a("<li>".$m[1]);
        } else if (preg_match('/^  (.*)/', $line, $m)){
          # keep line as is
          $a($m[1]."\n");
        } else {
          $end_list();
        }
      } else {
        if (preg_match('/^1\)(.*)/', $line, $m)) {
          $state = "in_numbered_list";
          $p_end();
          $a("\n<ul><li>");
          $a($m[1]);
        } elseif (preg_match('/^\*(.*)/', $line, $m)) {
          $state = "in_bullet_list";
          $p_end();
          $a("\n<ul><li>");
          $a($m[1]);
        } elseif (preg_match('/^[ ]*$/', $line)) {
          $p_end();
        } else {
          # keep line as is
          $p_start();
          $a($line."\n");
        }
      }
    }

    if (in_array($state, array( "in_numbered_list", "in_bullet_list"))){
      end_list();
    }
    return implode($result);
  }

  function rel_path($dest, $root = '', $dir_sep = '/') 
  {
    $root = explode($dir_sep, $root); 
    $dest = explode($dir_sep, $dest); 
    $path = '.'; 
    $fix = ''; 
    $diff = 0; 
    for($i = -1; ++$i < max(($rC = count($root)), ($dC = count($dest)));) 
    {
      if(isset($root[$i]) and isset($dest[$i])) 
      {
        if($diff) 
        {
          $path .= $dir_sep. '..'; 
          $fix .= $dir_sep. $dest[$i]; 
          continue; 
        }
        if($root[$i] != $dest[$i]) 
        {
          $diff = 1; 
          $path .= $dir_sep. '..'; 
          $fix .= $dir_sep. $dest[$i]; 
          continue; 
        }
      }
      elseif(!isset($root[$i]) and isset($dest[$i])) 
      { 
        for($j = $i-1; ++$j < $dC;) 
        {
          $fix .= $dir_sep. $dest[$j]; 
        }
        break; 
      } 
      elseif(isset($root[$i]) and !isset($dest[$i])) 
      { 
        for($j = $i-1; ++$j < $rC;){
          $fix = $dir_sep. '..'. $fix; 
        } 
        break; 
      } 
    } 
    return $path. $fix; 
  }

}

function wiki_to_html($target_directory, $wiki_file, array $to_html){
  global $pages;

  $pages[] = $wiki_file;

  # KISS: count /
  $path_depth = strlen(preg_replace('/[^\/]*/', '', $wiki_file));

  $html_file = $target_directory.'/'.$wiki_file.'.html';

  $patterns = array(
    # code blocks:
    '^(\{\{\{)((.|[\n])*)\}\}\}$',

    # links
    '(\[\[)([^\]]*)\]\]',
  );

  $html = preg_replace_callback(
          '/'.implode('|', $patterns).'/m',
          function($m) use(&$to_html, $wiki_file){
            global $reference_pages;

            // each pattern starts at later index, so drop all empty items, so 
            // that 0 always is the identifying string

            $s = $m[0]; array_shift($m);
            while($m[0] == '') array_shift($m);

            switch ($m[0]) {
              case '{{{':
                return $to_html['code_block']($m[1]);
                break;
              case '[[':
                if (preg_match('/^([^:]+:\/\/[^|]*)(|.*)?$/', $m[1], $m2)){
                  // external url
                  $href = $m2[1];
                  $alt = '';
                  $label = empty($m2[2]) ? $href : substr($m2[2], 1);
                  return sprintf('<a href="%s" alt="%s">%s</a>', $href, $alt, $label);
                } else {
                  // internal url
                  $reference_pages[] = $m[1];
                  return $to_html['wikilink']($m[1], rel_path('./'.$m[1], dirname($wiki_file)));
                }
                break;
              default:
                throw new Exception('bad replacement: '.var_export($m, true));
            };
          },
          process_lists(file_get_contents($wiki_file))
          );

  system('mkdir -p '. escapeshellcmd(dirname($html_file)));

  $html = $to_html['page']($wiki_file_rel_path, $html);

  file_put_contents($html_file, $html);
}

// clean: 
system('rm -fr '.$target_directory);

mkdir($target_directory);

foreach ($wiki_files as $wiki_file) {
  wiki_to_html($target_directory, $wiki_file, $to_html);
}

// TODO: check for broken links

$pages_path_as_key = array_flip($pages);
foreach ($reference_pages as $referenced_page) {
  if (!isset($pages_path_as_key[$referenced_page]))
    echo "WARNING: bad reference ".$referenced_page."\n";
}
