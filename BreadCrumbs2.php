<?php
/*
 * This file is part of Extension:BreadCrumbs2
 *
 * Copyright (C) 2007; Eric Hartwell and Ike Hecht.
 *
 * Distributed under the terms of the CC-BY-3.0 license.
 * Terms and conditions of the license can be found at
 * <https://creativecommons.org/licenses/by/3.0/>
 *
 * @author Eric Hartwell (http://www.ehartwell.com/InfoDabble/BreadCrumbs2)
 * @author Ike Hecht
 * @license CC-BY-3.0
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'BreadCrumbs2' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['BreadCrumbs2'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for the BreadCrumbs2 extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the BreadCrumbs2 extension requires MediaWiki 1.29+' );
}
