<?php
/***
 * BreadCrumbs2.php
 * @version 0.9
 * @date September 6, 2007
 * @author Eric Hartwell (http://www.ehartwell.com/InfoDabble/BreadCrumbs2)
 * @license Creative Commons Attribution 3.0

This extension generates "breadcrumbs" in the web navigation sense ("Where am I?")

To activate the functionality of this extension include the following in your
LocalSettings.php file:
 require_once('$IP/extensions/BreadCrumbs2.php');

Offered to the community for any use whatsoever with no restrictions other
than that credit be given to Eric Hartwell, at least in the source code,
according to the Creative Commons Attribution 3.0 License.
***/

# Change these constants to customize your installation
define ("DELIM", '@');							 // Delimiter/marker for parameters and keywords
define ("CRUMBPAGE", 'MediaWiki:Breadcrumbs');	 // Default is 'MediaWiki:Breadcrumbs'

# Standard sanity check
if ( !defined( 'MEDIAWIKI' ) ) {
   echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
   die( -1 );
}

# Credits for Special:Version
$wgExtensionCredits['other'][] = array(
		'name' => 'BreadCrumbs2',
		'version' => '0.9',
		'author' => 'Eric Hartwell',
		'url' => 'https://www.mediawiki.org/wiki/Extension:BreadCrumbs2',
		'description' => 'Implements a Breadcrumb navigation based on categories'
);

$wgResourceModules['ext.breadcrumbs2'] = array(
	'styles' => 'BreadCrumbs2.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'BreadCrumbs2',
);

# Hook function modifies skin output after it has been generated
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'buildBreadcrumbs';
$wgHooks['BeforePageDisplay'][] = 'addBreadCrumbs2Modules';

function addBreadCrumbs2Modules( OutputPage &$out, Skin &$skin ) {
	$out->addModules( 'ext.breadcrumbs2' );

	return true;
}

# This is the main function. Identify the categories for the current page,
# then locate the first match in the navigation list.
function buildBreadcrumbs( $skin, $template ) {
	# Only show breadcrumbs when viewing the page, not editing, etc.
	# The following line should perhaps utilize Action::getActionName( $skin->getContext() );
	if ( $skin->getRequest()->getVal( 'action', 'view') != 'view' ) {
		return true;
	}

	# Get the list of categories for the current page
	$categories = $skin->getOutput()->getCategories();

	$title = $skin->getRelevantTitle();

	# Treat the namespace as a category too
	if ( $title->getNsText() ) {
		$categories[] = $title->getNsText();
	}

	$crumbs = matchFirstCategory( CRUMBPAGE, $categories );

	# add current title
	$breadcrumb = trim( $crumbs[0] . ' ' . $title->getText() );
	$breadcrumbHTML =  Xml::openElement( 'div', array ( 'id' => 'breadcrumbs2' ) ) . $breadcrumb . Xml::closeElement( 'div' );
	$skin->getOutput()->prependHTML( $breadcrumbHTML );

	# If the current page is a category page, add it to the list
	# We didn't add it before because we don't want Category > Category'
	$pagecat = strstr( $title->getPrefixedText(), 'Category:' );
	if ( $pagecat !== FALSE )
		$categories[] = substr( $pagecat, strlen('Category:') );
	# If it's not a category page, try for an exact match of the title (e.g. 'Main')
	else
		$categories[] = $title->getText();

	# Mark the corresponding tab of the sidebar as active
	$crumbs = matchFirstCategory( CRUMBPAGE, $categories );
	if ( !empty($crumbs[1]) ) {
		# See if there's a corresponding link in the sidebar and mark it as active.
		# This is especially useful for skins that display the sidebar as a tab bar.
		if ( method_exists( $template, 'setActiveSidebarLink' ) ) {
			# The DynamicSkin extension can build the tabs (sidebar) dynamically,
			# and not necessarily from $template->data['sidebar'], so DynamicSkin 
			# and derived skins have a setActiveSidebarLink() function
			$template->setActiveSidebarLink( $crumbs[1] );
		} else {
			# Normal skins use the global sidebar data
			foreach ( $template->data['sidebar'] as $bar => $cont ) {
				foreach ( $cont as $key => $val ) {
					if ( $val['text'] == $crumbs[1] )
					{
						$template->data['sidebar'][$bar][$key]['active'] = true;
						break;
					}
				}
			}
		}
	 }

	# Finally, see if we should change the site logo
	# Don't go overboard with this... subtle is better.
	if ( !empty( $crumbs[2] ) ) {
		global $wgLogo, $wgScriptPath;
		$wgLogo = $wgScriptPath . '/' . $crumbs[2];
	}

	return true;
}

# Look up the menu corresponding to the first matching category from the list
function matchFirstCategory( $menuname, $categories ) {
	# First load and parse the template page
	$content = loadTemplate( $menuname );
	# Navigation list
	$breadcrumb = '';
	preg_match_all( "`<li>\s*?(.*?)\s*</li>`", $content, $matches, PREG_PATTERN_ORDER );

	# Look for the first matching category or a default string
	foreach ( $matches[1] as $nav ) {
		$pos = strpos( $nav, DELIM );				// End of category
		if ( $pos !== false ) {
			$cat = trim( substr( $nav, 0, $pos ) );
			$crumb = trim( substr( $nav, $pos + 1 ) );
			// Is there a match for any of our page's categories?
			if ( $cat == 'default' ) {
				$breadcrumb = $crumb;
			}
			else if ( in_array( $cat, $categories ) ) {
				$breadcrumb = $crumb;
				break;
			}
		}
	}

	return normalizeParameters( $breadcrumb, DELIM, 3 );
}

# Loads and preprocesses the template page
function loadTemplate( $title ) {
	global $wgUser, $wgParser;

	$title = Title::newFromText( "$title" );
	$template = getPageText( $title );
	if ( $template ) {
		# Drop leading and trailing blanks and escape delimiter before parsing
		# Substitute a few skin-related variables before parsing
		$template = preg_replace('/(^\s+|\s+$)/m', '', $template );
		$template = str_replace( DELIM.DELIM.DELIM, "\x07", $template);
		$template = preg_replace_callback(
			'/'.DELIM.DELIM.'(.*?)'.DELIM.DELIM.'/',
			"translate_variable",
			$template
		);

		# Use the parser preprocessor to evaluate conditionals in the template
		# Copy the parser to make sure we don't trash the parser state too much
		$lparse = clone $wgParser;
		$template = $lparse->parse( $template, $title, ParserOptions::newFromUser($wgUser) );
		$template = str_replace( '&nbsp;', ' ', $template->getText() );
		return $template ;
	}

	return '';
}

# Normalize a delimited parameter line: trim leading and trailing blanks,
# restore escaped delimiter characters, add null elements until all optional
# parameters are accounted for, and drop extra parameters
function normalizeParameters( $input, $delimiter, $count ) {
	 # Split the parameters into an array
	$params = explode( $delimiter, $input );
	$output = array();
	for ( $i = 0 ; $i < $count ; $i++ ) {
		$output[] = str_replace( "\x07", $delimiter, ($i < count( $params )) ? trim( $params[$i] ) : '' );
	}
	return $output ;
}

# Returns HTML text for the specified pseudo-variable
function translate_variable( $matches ) {
	$tag = $matches[1];
	global $wgParser, $wgUser;

	switch ( strtoupper($tag) ) {

	case 'USERGROUPS':			 // @@USERGROUPS@@ pseudo-variable: Groups this user belongs to
		if (!is_null($wgParser->mOutput)) {
			$wgParser->disableCache(); // Mark this content as uncacheable
		}
		return( implode( ",", $wgUser->getGroups() ) );

	case 'USERID':				 // @@USERID@@ pseudo-variable: User Name, blank if anonymous
		if (!is_null($wgParser->mOutput)) {
			$wgParser->disableCache(); // Mark this content as uncacheable
		}
		# getName() returns IP for anonymous users, so check if logged in first
		return( $wgUser->isLoggedIn() ? $wgUser->getName() : '' );

	}
}

/**
 * Gets the text contents of a page with the passed-in Title object.
 * Code graciously provided by Semantic Forms
 */
function getPageText( $title ) {
	if ( method_exists( 'WikiPage', 'getContent' ) ) {
		// MW 1.21+
		$wikiPage = new WikiPage( $title );
		$content = $wikiPage->getContent();

		if ( $content !== null ) {
			return $content->getNativeData();
		} else {
			return null;
		}
	} else {
		// MW <= 1.20
		$article = new Article( $title );
		return $article->getContent();
	}
}
