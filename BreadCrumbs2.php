<?php
/**
 * BreadCrumbs2.php
 * @version 1.3.1
 * @author Eric Hartwell (http://www.ehartwell.com/InfoDabble/BreadCrumbs2)
 * @author Ike Hecht
 * @license Creative Commons Attribution 3.0

  This extension generates "breadcrumbs" in the web navigation sense ("Where am I?")

  To activate the functionality of this extension include the following in your
  LocalSettings.php file:
  require_once "$IP/extensions/BreadCrumbs2/BreadCrumbs2.php";

  Offered to the community for any use whatsoever with no restrictions other
  than that credit be given to Eric Hartwell, at least in the source code,
  according to the Creative Commons Attribution 3.0 License.
 */

#Change these constants to customize your installation
define( 'DELIM', '@' );  // Delimiter/marker for parameters and keywords
define( 'CRUMBPAGE', 'MediaWiki:Breadcrumbs' );  // Default is 'MediaWiki:Breadcrumbs'

# Standard sanity check
if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This is an extension to the MediaWiki package and cannot be run standalone.\n";
	die( -1 );
}

# Credits for Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'BreadCrumbs2',
	'version' => '1.3.1',
	'author' => 'Eric Hartwell', 'Ike Hecht',
	'url' => 'https://www.mediawiki.org/wiki/Extension:BreadCrumbs2',
	'description' => 'Implements a Breadcrumb navigation based on categories',
	'license-name' => 'CC-BY-3.0'
);

$wgAutoloadClasses['BreadCrumbs2'] = __DIR__ . '/BreadCrumbs2.class.php';
$wgAutoloadClasses['BreadCrumbs2Hooks'] = __DIR__ . '/BreadCrumbs2.hooks.php';

# Hook function modifies skin output after it has been generated
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'BreadCrumbs2Hooks::onSkinTemplateOutputPageBeforeExec';

/**
 * If breadcrumbs are defined for this page, remove the link back to the base page.
 */
$wgBreadCrubs2RemoveBasePageLink = false;

/**
 * If no breadcrumbs are defined for this page, show nothing.
 */
$wgBreadCrubs2HideUnmatched = false;
