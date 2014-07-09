<?php 
/**
 * This file licensed under the terms of the GPL v2 or later.
 * It depends on having gutenberg-catalog-sqlite https://github.com/emilis/gutenberg-catalog-sqlite
 * It expects to have gutenberg-catalog-sqlite run in the same directory such that the sqlite file is in gutenberg-catalog-sqlite-master/data/catalog.sqlite relative to its directory
 * Initially created by Joe Morris, joe@xenotropic.net
 * Latest version of this file https://github.com/xenotropic/gutenovox
 */

// caching script from http://wesbos.com/simple-php-page-caching-technique/
// define the path and name of cached file
$cachefile = 'cached-files/'.date('M-d-Y').'.cache';
// define how long we want to keep the file in seconds. I set mine to 5 hours.
$cachetime = 18000;
// Check if the cached file is still fresh. If it is, serve it up and exit.
if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
  readfile($cachefile);
  exit;
}
// if there is either no file OR the file to too old, render the page and capture the HTML.
ob_start();
// end of caching script (more at end)

header('Content-type: text/html; charset=utf-8'); 
$script = basename ( __FILE__ );

?>

<html>
<head>
<link rel="stylesheet" type="text/css" href="./style.css">
<script src="sorttable.js"></script>

  <title>Gutenovox</title>

        <!-- meta -->
        <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=9,chrome=1">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no" />

    <meta name="author" content="xenotropic.net">
        <meta name="description" content="Gutenovox - Finding Gutenberg texts to read for LibriVox">
        <meta name="keywords" content="Librivox, Gutenberg, etexts, audiobooks">

        <!-- we are minifying and combining all our css, use styles.css for non-minified -->
        <link href="assets/css/minified.css.php" rel="stylesheet">

        <!-- grab jquery from google cdn. fall back to local if offline -->
    <script src="assets/js/jquery.js"></script>

    <!-- asynchronous google analytics. change UA-XXXXX-X to your site's ID -->
        <script>
  var _gaq=[['_setAccount','UA-26124742-1'],['_trackPageview']];
(function(d,t){
  var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
  g.src=('https:'==location.protocol?'//ssl':'//www')+'.google-analytics.com/ga.js';
  s.parentNode.insertBefore(g,s)
    }(document,'script'));
        </script>

</head>

<body>
<div style="background-image:url(assets/img/backgrounds/bg_vichy.png); bottom:0">
<div class="boxed bg-whitewall" style="padding: 20px">

<div class="two_third">  <p><h1><a href="<? echo $script; ?>">Gutenovox</a></h1>Searches the Project Gutenberg catalog and shows which works have been recorded in Librivox. Some recordings do not show up, so double-check against the official LibriVox catalog to be sure. Click on column headings to sort.
</div>
<br clear="all">
																								 <p> &nbsp; <p>
																								 <div class="one_full"><form>Search categories: <input  class="fifty green" placeholder="e.g., 'stories', 'history', 'tales', or 'science fiction'" type=text name=cat_search> <input type=submit value="Submit" class="button green small"></form></div>
<br clear="all">																							   
	    <div class="one_full"><form>Search last name: &nbsp;<input class="fifty green" type=text name=author placeholder="e.g., 'Twain', 'Dickinson', or 'Vonnegut'"> <input type=submit value="Submit" class="button green small"></form></div>
<br clear="all">
<hr>
<?


if ($_GET['author'] != null || 
    $_GET['title'] != null || 
    $_GET['author_gid'] != null ||
    $_GET['cat_search'] != null  ||
    $_GET['lcsh'] != null ) {
  
  $db_gb = new PDO ('sqlite:./gutenberg-catalog-sqlite-master/data/catalog.sqlite');
  $db_gb -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db_lv = new PDO ('sqlite:./librivox.sqlite3');
  $db_lv -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt;
  
  if ($_GET['author'] !='') {
    // TODO: insert three character limit on searches
    $contributors = array();  // need to keep track so as to be able to combine ids for those that are both author and contributor
    
    $stmt_co = $db_gb->prepare ("select * from contributors where name like :author_name order by name");
    $stmt_co->bindValue (':author_name', '%' .  $_GET['author'] . '%');
    $stmt_co->execute();

    while ($row = $stmt_co->fetch(PDO::FETCH_ASSOC) ) {
      $contributors[$row['id']] = $row['name'];;
      $n++;
    }
    
    $stmt = $db_gb->prepare ("select * from creators where name like :author_name order by name");
    $stmt->bindValue (':author_name', '%' .  $_GET['author'] . '%');
    $stmt->execute();
    
    echo "<table class=\"sortable\"><tr><th>Author</th></tr>";
    $n=0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
      $co_id = array_search ($row['name'], $contributors); // returns contributor key if it exists for this name, otherwise returns false
      if ( $co_id == false ) {
	$co_id = null; // no contributor, so putting blank for parameter
      } else {
	unset ($contributors[$co_id]); // we now have this persons id in $co_id, now remove from the $contributors array so they don't come up again in the contributors-only table
      }
      
      echo "<tr><td><a href=\"./" . $script . "?author_gid=" . $row['id'] . "&contrib_gid=".$co_id."\">" . $row['name'] . "</a></td></tr>";
      $n++;
    }
    if ( $n == 0 ) echo "No authors found like <b>'". $_GET['author'].".'</b>";
    echo "</table>";


    // finding as contributors
    
    echo "<table class=\"sortable\"><tr><th>Contributor</th></tr>";
    $n=0;
    foreach ( $contributors as $co_id=>$co_name ) {
      echo "<tr><td><a href=\"./" . $script . "?contrib_gid=" . $co_id . "\">" . $co_name . "</a></td></tr>";
      $n++;
    }
    if ( $n == 0 ) echo "<tr><td>No contributors found like <b>'". $_GET['author'].".'</b></td></tr>";
    echo "</table>";
    /* */
  }  // end author

  if ($_GET['lcsh'] != '' ) {
    $stmt = $db_gb->prepare ("SELECT * from lcsh_subjects WHERE id=:lcsh_id");
    $stmt->bindValue(':lcsh_id', $_GET['lcsh']);
    $stmt->execute();
    $category = "not found";
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
      $category = $row['name'];
    }
    $stmt = $db_gb->prepare ("SELECT c.name, c.id, b.etext_id, b.data FROM books AS b JOIN lcsh2books AS l2b ON l2b.book_id = b.id JOIN creators AS c ON c2b.creator_id = c.id  JOIN creators2books AS c2b ON c2b.book_id = b.id WHERE l2b.lcsh_id=:lcsh_id");
    $stmt->bindValue(':lcsh_id', $_GET['lcsh']);
    $stmt->execute();
    $first = true;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
      if ( $first )  {
	echo "Category: <b>$category</b> <p> &nbsp;<p><table class=sortable>";
	echo "<colgroup> <col span=\"1\" style=\"width: 25%;\"> <col span=\"1\" style=\"width: 50%;\"> <col span=\"1\" style=\"width: 10%;\"> <col span=\"1\" style=\"width: 10%;\">  </colgroup>";
	echo "<tr><th>Author</th><th>Title</th><th>LibriVox Recordings</th><th>Est. Read Time</th></tr>";
	$first = false;
      }
      $title = $row ['data'];
      $title = substr ( $title, strpos ( $title, '{"title":["') + 11 ); // sqlite database title capped to 255 chars but 'data' field isn't, so need to get title from data field
      $title = substr ( $title, 0, strpos ( $title, "\"]") );
	$title = str_replace ( '\n', " - ", $title); 
	$title = str_replace ( '\"', "\"", $title); 

      //      echo "<tr><td><a href=\"" . $script . "?author_gid=" . $row['id'] . "\">" . $row['name'] . " </a></td><td> " . $title . " </td><td> ";
      echo "<tr><td><a href=\"" . $script . "?author_gid=" . $row['id'] . "\">" . $row['name'] . " </a></td><td><a target=\"_guten\"href=\"http://www.gutenberg.org/ebooks/" . $row['etext_id'] . "\">" . $title . "</a> </td><td> ";  // to see gutenberg ids

      $recording_urls = getLVRecordingsByTitle ($title, $row['name']);
      if ( ! empty ($recording_urls) ) {
	$n = 1;
	foreach ( $recording_urls as $recording_url ) {
	  echo "<a href=\"" . $recording_url . "\">[" . $n . "]</a> ";  
	  $n++;
	}
      } else echo "None found";
      
      echo " </td><td>";
      echo getReadTimeForEtext ($row['etext_id']) . "</td></tr>"  ;
    }
    echo "</table>";
  }

  if ($_GET['title'] != '' ) {
    
  } // end title 
  if ($_GET['cat_search'] != '' ) {  
    echo "<table class=\"sortable\"><tr><th>Categories like ".$_GET['cat_search']."</th></tr>";
    $stmt = $db_gb->prepare ( "select * from lcsh_subjects where name like :subject");
    $stmt->bindValue ( ':subject', "%" . $_GET['cat_search'] . "%" );
    $stmt->execute ();
    $n = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
      echo "<tr><td><a href=\"./". $script . "?lcsh=" . $row['id'] . "\">" . $row['name']. "</a></li></td></tr>";
      $n++; 
    }
    echo "</table>";
    if ( $n == 0 ) echo "No categories found like <b>'". $_GET['cat_search'].".'</b>";
  }
  if ( $_GET['author_gid'] !='' || $_GET['contrib_gid'] !='' ) { // getting all books for a gutenberg author id
    if ( $_GET['author_gid'] !='' ) {
      $stmt = $db_gb->prepare ("SELECT c.name, b.id, b.etext_id, b.data FROM books AS b JOIN creators2books AS c2b ON c2b.book_id = b.id JOIN creators AS c ON c2b.creator_id = c.id WHERE c2b.creator_id=:gid");
      $stmt->bindValue (':gid', $_GET['author_gid']);
      $stmt->execute();
      $first = true;
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
	if ( $first )  {
	  echo "<table class=\"sortable\"><tr><th><u>Titles by ".$row['name']."</u> &nbsp; </th><th>LibriVox recordings &nbsp;</th><th>Est. Read Time</th>";
	  //	  echo "<th>etext_id</th>";
	  echo "</tr>";
	  $first = false;
	  if (strpos ( $row['name'], "," ) == false) {
	    $author_last = $row['name'];
	  } else {
	    $author_last = substr ( $row['name'], 0, strpos ( $row['name'], "," ) );
	  }
	  $librivox_retval = file_get_contents ('https://librivox.org/api/feed/audiobooks?author=' . $author_last . '&format=serialized&fields={id,title,authors,url_text_source,url_librivox}'  );
	  $librivox_array = unserialize ($librivox_retval);
	  $librivox_entries = $librivox_array['books']; 
	}
	$title = $row ['data'];
	$title = substr ( $title, strpos ( $title, '{"title":["') + 11 ); // sqlite database title capped to 255 chars but 'data' field isn't, so need to get title from data field
	$title = substr ( $title, 0, strpos ( $title, "\"]") );
	$title = str_replace ( '\n', " - ", $title); 
	$title = str_replace ( '\"', "\"", $title); 
	//	echo "<tr><td>$title (".$row['etext_id'].")</td><td>";

	// <a target=\"_guten\"href=\"http://www.gutenberg.org/ebooks/" . $row['etext_id'] . "\">" . $title . "</a>
	echo "<tr><td><a target=\"_guten\"href=\"http://www.gutenberg.org/ebooks/" . $row['etext_id'] . "\">" . $title . "</a></td><td>";
	$recording_urls = getLVRecordingsForGID ( $librivox_entries, $row['etext_id'], $title );
	if ( ! empty ($recording_urls) ) {
	  $n = 1;
	  foreach ( $recording_urls as $recording_url ) {
	    echo "<a href=\"" . $recording_url . "\">[" . $n . "]</a> ";  
	    $n++;
	  }
	} else echo "None found";
	echo "</td><td>".  getReadTimeForEtext ($row['etext_id']) ."</td></tr>";
      }
      echo "</table>";
    } // end author table

    if ( $_GET['contrib_gid'] !='' ) {
      $stmt = $db_gb->prepare ("SELECT c.name, b.id, b.etext_id, b.data FROM books AS b JOIN contributors2books AS c2b ON c2b.book_id = b.id JOIN contributors AS c ON c2b.contributor_id = c.id WHERE c2b.contributor_id=:cid");
      $stmt->bindValue (':cid', $_GET['contrib_gid']);
      $stmt->execute();
      $first = true;
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
	if ( $first )  {
	  echo "<table class=\"sortable\"><tr><th><u>".$row['name']."</u> as contributor &nbsp; </th><th>LibriVox recordings</th><th>Est. Read Time</th></tr>";
	  $first = false;
	  if (strpos ( $row['name'], "," ) == false) {
	    $author_last = $row['name'];
	  } else {
	    $author_last = substr ( $row['name'], 0, strpos ( $row['name'], "," ) );
	  }
	  $librivox_retval = file_get_contents ('https://librivox.org/api/feed/audiobooks?author=' . $author_last . '&format=serialized&fields={id,title,authors,url_text_source,url_librivox}'  );
	  $librivox_array = unserialize ($librivox_retval);
	  $librivox_entries = $librivox_array['books']; 
	  // echo "Entries for $author_last: <pre>"; print_r ( $librivox_entries );	echo "</pre>"; // for API testing
	}
	$title = $row ['data'];
	$title = substr ( $title, strpos ( $title, '{"title":["') + 11 ); // sqlite database title capped to 255 chars but 'data' field isn't, so need to get title from data field
	$title = substr ( $title, 0, strpos ( $title, "\"]") );
	$title = str_replace ( '\n', " - ", $title); 
	$title = str_replace ( '\"', "\"", $title); 

	echo "<tr><td>$title</td><td>";
	$recording_urls = getLVRecordingsForGID ( $librivox_entries, $row['etext_id'], $row['title'] );
	if ( ! empty ($recording_urls) ) {
	  $n = 1;
	  foreach ( $recording_urls as $recording_url ) {
	    echo "<a href=\"" . $recording_url . "\">[" . $n . "]</a> ";  
	    $n++;
	  }
	} else echo "None found";
	echo "</td><td>".  getReadTimeForEtext ($row['etext_id']) ."</td></tr>";
      }
      echo "</table>";
    } // end contrib table

    
  } // end author/contrib_gid if  
} 

/**
$librovox_list is associative array from librivox API
$ebook_id is Project Gutenberg ebook id
$gb_title is Project Gutenberg title
@return array of librivox recording URLs for the book with the $ebook_id
 */

function getLVRecordingsForGID ( $librivox_list, $ebook_id, $gb_title ) {
  global $db_lv;
  $recordings = array ();
  // echo " -- Checking ebook_id: " . $ebook_id . " against " . count ( $librivox_list ) . " entries -- ";
  foreach ( $librivox_list as $librivox_entry ) {  //  want to move this to sqlite
    $text_url = $librivox_entry['url_text_source'];
    if ( $text_url != null ) {
      $text_url = str_replace ( 'ebooks', 'etext', $text_url );
      $lv_ebook_id = substr ( $text_url, strpos ( $text_url, '/etext/') + 7 );
      if ( $lv_ebook_id == $ebook_id ) $recordings['$ebook_id'] = $librivox_entry['url_librivox'];
    }
  }
  $stmt = $db_lv->prepare ("select s.author,s.title,s.parent_id,a.url_librivox from sections AS s JOIN audiobooks as a ON s.parent_id=a.id WHERE s.title=:title COLLATE NOCASE" );
  $stmt->bindValue ( ':title', $gb_title );
  $stmt->execute();
  
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
    $recordings[$row['parent_id']] = $row['url_librivox'];
    //    if ( ! array_key_exists ($row['parent_id'], $recordings) ) $recordings[$row['parent_id']] = $row['url_librivox'];
  }
   
  return $recordings;
}


function getLVRecordingsByTitle ( $title, $author ) {
  global $db_lv;
  $recordings = array ();
  $author_last = substr ( $title, 0, strpos ($author, ','));

  $stmt = $db_lv->prepare ( "SELECT id, url_librivox FROM audiobooks WHERE title=:title COLLATE NOCASE;" );
  $stmt->bindValue ( ':title', $title );
  $stmt->execute();
  
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
    $recordings[$row['id']] = $row['url_librivox']; 
    // need to do some kind of author based checking here
  }

  $stmt = $db_lv->prepare ("SELECT s.author,s.title,s.parent_id,a.url_librivox FROM sections AS s JOIN audiobooks AS a ON s.parent_id=a.id WHERE s.title=:title COLLATE NOCASE" );
  $stmt->bindValue ( ':title', $title );
  $stmt->execute();
  
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
    $recordings[$row['parent_id']] = $row['url_librivox'];
    // need to do some kind of author based checking here

  }
  return $recordings;
}

function getReadTimeForEtext ( $etext_id ) {
  global $db_gb;
  $stmt = $db_gb->prepare ("SELECT * FROM files WHERE etext_id=:id" );
  $stmt->bindValue (':id', $etext_id);
  $stmt->execute();
  $file_size = -1;
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
    if ( endsWith ( $row['url'], 'txt' ) ) $file_size = $row['size'];
  }  
  if ( $file_size == -1 ) return ("Unknown"); 
  $file_size -= 18233; // Not counting gutenberg license

  // return ( round ($file_size/1421)  . " mins"  );
  //  if ( $file_size < 85260 ) return ( (round ($file_size/1421) ) . " mins");
  $hours = floor ( ($file_size/900)/60  );
  $minutes = (round ( $file_size/900) ) % 60;
  return ( "$hours h $minutes min" );
}

function endsWith($haystack, $needle)
{
  $length = strlen($needle);
  if ($length == 0) {
    return true;
  }

  return (substr($haystack, -$length) === $needle);
}


?>


<p>&nbsp;<p><small><a href="https://github.com/xenotropic/gutenovox">Source code</a> &nbsp; | &nbsp; <a href="mailto:joe@xenotropic.net?Subject=Gutenovox">Email me</a></small>
</div>
</div>

<div style="background-image:url(assets/img/backgrounds/bg_vichy.png)">
</div>
</body>
</html>

<?php
  // From http://wesbos.com/simple-php-page-caching-technique/
  // We're done! Save the cached content to a file
  $fp = fopen($cachefile, 'w');
fwrite($fp, ob_get_contents());
fclose($fp);
// finally send browser output
ob_end_flush();
?>
