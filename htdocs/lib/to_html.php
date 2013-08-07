<?php
// PHP 5.3

/* the parsing is done in two steps
 * process_lists: line based stateful parser rewriting lists

 preg_replace_callback replacing [[ and {{{

Then last bad links are reported
*/

{ // config

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

    <style type="text/css" media=screen>

      h1 { border-bottom: 3px dotted #007f00; background-color: #DFD; max-width: 640px; }
      h2 { border-bottom: 2px dotted #007f00; background-color: #DFD; max-width: 640px; }
      h3 { border-bottom: 1px dotted #007f00; background-color: #DFD; max-width: 640px; }

      span.inline_code {
        background-color:#EEE;
        padding: 2px;
        padding-left: 4px;
        padding-right: 4px;
      }
      pre.code {
        max-width: 600px;

        background-color:#EEE; 
        padding: 10px;
        margin-left: 20px;

        border-radius: 5px; 
        -moz-border-radius: 5px; 
        -webkit-border-radius: 5px; 
      }

      th {
        background-color: #DFD;
        font-weight: bolder;
      }
      td, th {
        border: 1px dotted #03476F;
      }
      td, th {
        padding: .4em;
        color: #222;
      }
      p {
        max-width: 400px;
      }

    </style>

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

  function process_lists($file_name, $s){
    // I'm not that happy about this code, but it'll work
    // on 1) start numbered list (item)
    // on *) start bullet list (item)
    // on "  " continue list
    // else stop list or keep line

    $result = array();
    $state = '';
    $p_end = null;

    $features = array();
    $matrix = array();

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
    $code_start = function()use(&$a){ $a('<pre class="code">'); };
    $code_end = function()use(&$a){ $a('</pre>'); };

    $render_feature_matrix = function() use(&$a, &$features, &$matrix){
      /*
      $a('<table><tr><th></th>');
      foreach ($features as $f => $f_desc) {
        $a(sprintf('<th alt="%s">%s</th>',  quote($f_desc), quote($f)));
      }
      $a('</tr>');
      foreach ($matrix as $name => $features_of_name) {
        $a(sprintf('<tr><th>%s</th>', $name));
        foreach ($features as $f => $f_desc) {
          if (in_array($f, $features_of_name)){
            $a(sprintf("<td>yes</td>"));
          } else {
            $a(sprintf("<td></td>"));
          }
        }
        $a('</tr>');
      }
       */

      $a(sprintf('<strong>%s comparison:</strong>', implode(', ', array_keys($matrix))));
      $a('<table><tr><th>feature</th>');
      foreach ($matrix as $name => $features_of_name) {
        $a(sprintf('<th>%s</th>',  quote($name)));
      }
      $a('<th>feature description</th></tr>');
      foreach ($features as $f => $f_desc) {
        $a(sprintf('<tr><th>%s</th>', quote($f)));
        foreach ($matrix as $name => $features_of_name) {
          if (in_array($f, $features_of_name)){
            $a(sprintf("<td>yes</td>"));
          } else {
            $a(sprintf("<td></td>"));
          }
        }
        $a(sprintf('<td>%s</td>', quote($f_desc)));
        $a('</tr>');
      }
      $a('</table>');
    };

    foreach (preg_split('/(\r\n|\n)/',$s) as $line) {
        $error = function($msg) use(&$file_name, $line) {
          die('ERROR parsing '.$file_name.' : '.$msg."\n".$line);
        };

        $line_empty = preg_match('/^[ ]*$/', $line);
        if ($state == "feature_matrix_reading_features"){
          // matrix: feature continuation
          if (preg_match('/^MATRIX/', $line)){
            $state = 'feature_matrix_reading_matrix';
          } else {
            if ($line_empty) continue;
            if (preg_match('/^(.*?)[ ]*::[ ]*(.*)/', $line, $m)){
              $features[$m[1]] = $m[2];
            } else {
              $error('unexpected matrix feature line ');
            }
          }
        } elseif ($state == "feature_matrix_reading_matrix"){

          // matrix continuation
          if ($line_empty){
            $render_feature_matrix();
            $state = '';
          } else {
            if (preg_match('/^(.*?)[ ]*::[ ]*(.*)/', $line, $m)){
              $matrix[$m[1]] = preg_split('/[ ]*,[ ]*/',$m[2]);
            } else {
              $error('unexpected matrix line ');
            }
          }
        } elseif ($state == "in_code_block"){
         // continue code block
         if (preg_match('/^}}}/', $line)){
           $code_end();
           $state = '';
         } else {
           $a(quote($line)."\n");
         }
       } else if (in_array($state, array( "in_numbered_list", "in_bullet_list"))){
        // continue lists
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
      } else if (preg_match('/^(=+) (.*)$/', $line, $m)){
        // parse headers
        $p_end();
        $l = strlen($m[1]);
        $a(sprintf("\n<h%d>%s</h%d>", $l, quote(preg_replace('/=*$/', '', $m[2])), $l));
      } else {
        if (preg_match('/^FEATURES/', $line, $m)) {
          $state = 'feature_matrix_reading_features';
          $features = array();
          $matrix = array();
        } else if (preg_match('/^1\)(.*)/', $line, $m)) {
          // start 1) list
          $state = "in_numbered_list";
          $p_end();
          $a("\n<ul><li>");
          $a($m[1]);
        } elseif (preg_match('/^\* (.*)/', $line, $m)) {
          // start * list
          $state = "in_bullet_list";
          $p_end();
          $a("\n<ul><li>");
          $a($m[1]);
        } elseif (preg_match('/^{{{$/', $line)) {
          // start code block
          $p_end();
          $code_start();
          $state = 'in_code_block';
        } elseif (preg_match('/^[ ]*$/', $line)) {
          $p_end();
        } else {
          # everything else
          # keep line as is
          $p_start();
          $a($line."\n");
        }
      }
    }

    if ($state == "feature_matrix_reading_matrix")
      $render_feature_matrix();

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



class WikiToHTML {

	public function __construct($url_prefix){
		$this->url_prefix = $url_prefix;
	}

	public function wiki_to_html($wiki_file_path, $wiki_file_contents, array $to_html){
		$this_obj = $this;

	  $patterns = array(
	    # inline code blocks {{{ }}} 
	    '({{{)(.*?)}}}',
	    # strong
	    '(\*\*)((.|[\n])*?)\*\*',
	    # links
	    '(\[\[)([^\]]+)\]\]',
	  );


	  $process_inline_elements = function($text)use(&$patterns, &$wiki_file_path, &$wiki_file_contents, &$this_obj, &$to_html){
		  return preg_replace_callback(
				  '/'.implode('|', $patterns).'/m',
				  function($m) use(&$to_html, $wiki_file_path, &$wiki_file_contents, &$this_obj){
					  global $reference_pages;

					  // each pattern starts at later index, so drop all empty items, so 
					  // that 0 always is the identifying string

					  $s = $m[0]; array_shift($m);
					  while($m[0] == '') array_shift($m);

					  switch ($m[0]) {
					  case '{{{':
						  return sprintf('<span class="inline_code">%s</span>', quote($m[1]));
					  case '**':
						  return sprintf('<strong>%s</strong>', $m[1]);
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
							  return $to_html['wikilink']($m[1], rel_path('./'.$m[1], dirname($wiki_file_path)));
						  }
						  break;
					  default:
						  throw new Exception('bad replacement: '.var_export($m, true));
					  };
				  },
				  $text
			  );
	  };
	  $html = $process_inline_elements(process_lists($wiki_file_path, $wiki_file_contents));
	  $html = 
		  '<div class="header">'
		  .$process_inline_elements("[[index]] [[".edit_page_url($wiki_file_path)."|edit]] [[".log_page_url($wiki_file_path)."|log]]")
		  .vim_wiki_search_form()
		  .'</div>'
		  .$html;
		
	  return $html;

	}
}
