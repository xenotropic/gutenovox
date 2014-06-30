<?php 
/**
 * This file licensed under the terms of the GPL v2 or later.
 * It depends on having gutenberg-catalog-sqlite https://github.com/emilis/gutenberg-catalog-sqlite
 * It expects to have gutenberg-catalog-sqlite run in the same directory such that the sqlite file is in gutenberg-catalog-sqlite-master/data/catalog.sqlite relative to its directory
 * Initially created by Joe Morris, joe@xenotropic.net
 * Latest version of this file https://github.com/xenotropic/gutenovox
 */

header('Content-type: text/html; charset=utf-8'); 

?>

<html>
<head>
<link rel="stylesheet" type="text/css" href="./style.css">
</head>

<body>

<p>  <b><u>Gutenovox</u></b> <p> Searches the Project Gutenberg catalog and shows where each work has been recorded by Librivox. Works inside collections do not show up as they are not returned by the Librivox API.
<p><form>Enter author name: <input type=text name=author><input type=submit></form>

<?


if ($_GET['author'] != null || $_GET['title'] != null || $_GET['author_gid'] != null ) {

  
  $db = new PDO ('sqlite:./gutenberg-catalog-sqlite-master/data/catalog.sqlite');
  $db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt;
  
  if ($_GET['author'] !='') {
    // insert three character limit on searches
    $stmt = $db->prepare ("select * from creators where name like :author_name limit 25");
    $stmt->bindValue (':author_name', '%' .  $_GET['author'] . '%');
    $stmt->execute();
    echo "Showing first 25 authors with name like '" . $_GET['author'] . "':";
    echo "<ul>";
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
    echo "<li><a href=\"./index.php?author_gid=" . $row['id'] . "\">" . $row['name'] . "</a></li>";
    
      } // end author
  }
  if ($_GET['title'] != '' ) {
    
  } // end title
  
  
  if ( $_GET['author_gid'] !='') { // getting all books for a gutenberg author id
    $stmt = $db->prepare ("SELECT c.name, b.id, b.etext_id, b.data FROM books AS b JOIN creators2books AS c2b ON c2b.book_id = b.id JOIN creators AS c ON c2b.creator_id = c.id WHERE c2b.creator_id=:gid");
    $stmt->bindValue (':gid', $_GET['author_gid']);
    $stmt->execute();
    $first = true;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
      if ( $first )  {
	echo "Showing books for author <b>".$row['name']."</b>. Numbers after the title link to Librivox recordings. Recordings in collections not shown. <ul>";
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
      echo "<li>$title ";
      $recording_urls = getLVRecordingsForGID ( $librivox_entries, $row['etext_id'], $row['title'] );
      if ( ! empty ($recording_urls) ) {
	$n = 1;
	foreach ( $recording_urls as $recording_url ) {
	  echo "<a href=\"" . $recording_url . "\">[" . $n . "]</a> ";  
	  $n++;
	}
      }
      echo "</li>";
    }
    echo "</ul>";
  }  
} 

/**
$librovox_list is associative array from librivox API
$ebook_id is Project Gutenberg ebook id
$gb_title is Project Gutenberg title
@return array of librivox recording URLs for the book with the $ebook_id
 */

function getLVRecordingsForGID ( $librivox_list, $ebook_id, $gb_title ) {
  $recordings = array ();
  // echo " -- Checking ebook_id: " . $ebook_id . " against " . count ( $librivox_list ) . " entries -- ";
  foreach ( $librivox_list as $librivox_entry ) {
    $text_url = $librivox_entry['url_text_source'];
    if ( $text_url != null ) {
      $text_url = str_replace ( 'ebooks', 'etext', $text_url );
      $lv_ebook_id = substr ( $text_url, strpos ( $text_url, '/etext/') + 7 );
      if ( $lv_ebook_id == $ebook_id ) $recordings[] = $librivox_entry['url_librivox'];
    }
  }
  return $recordings;
}

?>

</body>
</html>
