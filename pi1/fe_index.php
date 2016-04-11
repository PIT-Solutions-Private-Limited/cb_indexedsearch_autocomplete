<?php
// Exit, if script is called directly (must be included via eID in index_ts.php)
if (!defined ('PATH_typo3conf')) 	die ('Could not access this script directly!');

// Exit if user tries to fiddle with the values ;-)
if(!(t3lib_div::_GET('sr') < 1 || t3lib_div::_GET('sh') == t3lib_div::md5int(t3lib_div::_GET('sr') . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']))) {
	die('thx but no thx');
}
$obj = t3lib_div::makeInstance('tx_cb_indexedsearch_autocomplete_fe_index');
$obj->look_up();

// No need to waste more time here :-]
exit();


class tx_cb_indexedsearch_autocomplete_fe_index {

	/*
	* Do the setup bit
	*/
	function tx_cb_indexedsearch_autocomplete_fe_index() {

		
		// include class def
		//require_once(PATH_t3lib.'class.t3lib_page.php');
		
		// Initialize FE user object:
		$this->feUserObj = tslib_eidtools::initFeUser();
		
		// Connect to database:
		tslib_eidtools::connectDB();
		
		$this->pages = array(intval(t3lib_div::_GET('sr')));

		// hook for showing extension version
		if( intval(t3lib_div::_GET('sv')) > 0 ) {
			$_EXTKEY = "temp";
			require_once($GLOBALS['TYPO3_LOADED_EXT']['cb_indexedsearch_autocomplete']['siteRelPath'] . 'ext_emconf.php');
			die($EM_CONF[$_EXTKEY]['version']);
		}
		
		// Setup enablefields def
		tslib_fe::includeTCA();
		$GLOBALS['TSFE']->gr_list = $this->feUserObj->user['usergroup'];
		$this->pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
		$this->enableFields = $this->pageSelect->enableFields('pages');
		$this->multipleGroupsWhereClause = $this->pageSelect->getMultipleGroupsWhereClause('gr_list', 'index_phash');

		return true;
	}

	
	/*
	* Look up the pages the user can access
	*/
	function get_pages() {	
		$pages = implode(',', $this->pages);		
		while($pages = $this->get_subpages($pages)) {
			$this->pages = array_merge($this->pages, explode(',', $pages));
		}		
		return true;
	}	

	/*
	* Return subpages to $pages
	*/
	function get_subpages($pages) {		
		$new_pages = '';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'pages', "pid IN ($pages)" . $this->enableFields);		
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$new_pages .= $row['uid'] . ',';
		}		
		// Did we get any?
		if(strlen($new_pages) > 0) {
			$new_pages = substr($new_pages, 0, -1);
		}		
		return $new_pages;
	}
	

	/*
	* Look up the words and print them
	*/
	function look_up() {		
		$ll = t3lib_div::_GET( 'll' );
		$language = ( !empty( $ll ) ) ? intval( $ll ) : 0;
		$the_word_array = t3lib_div::trimExplode(' ', t3lib_div::_GET('sw'), 1);
		$the_final_word_array = array();
		$this->get_pages();
				
		// Look up the words
		foreach($the_word_array as $one_word) {
			
			if(strlen($one_word) < 3) {
				continue;
			}

			$condition =  'index_words.wid = index_rel.wid AND 
										 index_rel.phash = index_phash.phash AND index_phash.sys_language_uid = ' .$language. ' AND
										 (baseword LIKE '. $GLOBALS['TYPO3_DB']->fullQuoteStr("%$one_word%", 'index_words') . ' OR 
											 baseword LIKE '. $GLOBALS['TYPO3_DB']->fullQuoteStr("$one_word%", 'index_words') . ' OR 
											 baseword LIKE '. $GLOBALS['TYPO3_DB']->fullQuoteStr("%$one_word", 'index_words') . ') ' .
										$this->multipleGroupsWhereClause;

		 	$data = $GLOBALS['TYPO3_DB']->exec_SELECTquery( 'baseword, data_page_id', 'index_words, index_rel, index_phash',
															 $condition, '', '', '' );
			// Build the array
			$results = array();
			$word_page_index = array();
			while($value = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($data)) {
				// Final check against the pages we retreived on basis of the enablefields (starttime/endtime)
				if(in_array($value['data_page_id'], $this->pages)) {			
					$word_page_index[$value['data_page_id']][] = $value['baseword'];
					if(!isset($results[$value['baseword']])) {
						$results[$value['baseword']] = 1;
					} else {
						$results[$value['baseword']]++;
					}
				}
			}
			
			// For each word, count if its a subword from another word(s) in the array
			$the_words = array_keys($results);
			$final_results = array();
			foreach($results as $word => $count) {
				$final_count = 0;
				foreach($word_page_index as $one_page_array) {
					foreach($one_page_array as $one_word) {
						if(strpos($one_word, $word) !== false) {
							// We got a hit on this page
							$final_count++;
							// Dont count more, next page up!
							break;
						}
					}
				}
				$final_results[$word] = $final_count;
			}
			
			// Sort it
			arsort($final_results);

			// Save us from charset trouble (maybe ;-) )
			header('Content-type: text/html; charset=UTF-8');

			// Print it
			foreach($final_results as $word => $count) {			
				echo $word . '|' . $count . "\n";
			}
			return true;
		}
	}
}
?>