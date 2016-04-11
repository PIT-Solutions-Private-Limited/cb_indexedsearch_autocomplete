<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TYPO3_CONF_VARS['EXTCONF']['indexed_search']['pi1_hooks']['initialize_postProc'] = 'EXT:cb_indexedsearch_autocomplete/pi1/class.tx_cb_indexedsearch_autocomplete_pi1.php:&tx_cb_indexedsearch_autocomplete_pi1';

$TYPO3_CONF_VARS['FE']['eID_include']['cb_indexedsearch_autocomplete'] = 'EXT:cb_indexedsearch_autocomplete/pi1/fe_index.php';

?>
