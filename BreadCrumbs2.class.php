<?php
class BreadCrumbs2 {
	/**
	 * Full text for the breadcrumbs, if any
	 *
	 * @var string
	 */
	private $crumbPath;

	/**
	 * Sidebar text to look for
	 *
	 * @var string
	 */
	private $sidebarText;

	/**
	 * Path to the logo, to be appended to $wgScriptPath
	 *
	 * @var string
	 */
	private $logoPath;

	/**
	 * Final full breadcrumb path, including page title
	 * Will always contain at least the current page title
	 *
	 * @var string
	 */
	private $breadcrumb;

	/**
	 * Stores the title of the first category in the page. Used for FIRSTCATEGORY variable.
	 *
	 * @var string
	 */
	private $firstCategoryInPage;

	/**
	 * Does this page have breadcrumbs defined for it?
	 *
	 * @return boolean
	 */
	public function hasBreadCrumbs() {
		return (bool) $this->crumbPath != '';
	}

	/**
	 * Get the full output to be inserted into the wiki page
	 *
	 * @return string HTML
	 */
	public function getOutput() {
		return Html::rawElement( 'div', array( 'id' => 'breadcrumbs2' ), $this->breadcrumb );
	}

	/**
	 *
	 * @param array $categories
	 * @param Title $title
	 * @return boolean
	 */
	public function __construct( array $categories, Title $title ) {
		if ( !empty( $categories ) ) {
			$this->firstCategoryInPage = $categories[0];
		}

		/** @todo Support main namespace */
		# Treat the namespace as a category too
		if ( $title->getNsText() ) {
			$categories[] = $title->getNsText();
		}

		$crumbs = $this->matchFirstCategory( CRUMBPAGE, $categories );

		$this->crumbPath = $crumbs[0];

		# add current title
		$this->breadcrumb = trim( $this->crumbPath . ' ' . $title->getText() );

		# If the current page is a category page, add it to the list
		# We didn't add it before because we don't want Category > Category'
		$pagecat = strstr( $title->getPrefixedText(), 'Category:' ); //FIXME
		if ( $pagecat !== false ) {
			$categories[] = substr( $pagecat, strlen( 'Category:' ) ); //FIXME
		} else {
			# If it's not a category page, try for an exact match of the title (e.g. 'Main')
			$categories[] = $title->getText();
		}

		# Mark the corresponding tab of the sidebar as active
		$crumbs = $this->matchFirstCategory( CRUMBPAGE, $categories );
		$this->sidebarText = $crumbs[1];
		$this->logoPath = $crumbs[2];

		return true;
	}

	/**
	 * Look up the menu corresponding to the first matching category from the list
	 *
	 * @param string $menuname
	 * @param array $categories
	 * @return string
	 */
	function matchFirstCategory( $menuname, array $categories ) {
		# First load and parse the template page
		$content = $this->loadTemplate( $menuname );
		# Navigation list
		$breadcrumb = '';
		preg_match_all( "`<li>\s*?(.*?)\s*</li>`", $content, $matches, PREG_PATTERN_ORDER );

		# Look for the first matching category or a default string
		foreach ( $matches[1] as $nav ) {
			$pos = strpos( $nav, DELIM ); // End of category
			if ( $pos !== false ) {
				$cat = trim( substr( $nav, 0, $pos ) );
				$crumb = trim( substr( $nav, $pos + 1 ) );
				// Is there a match for any of our page's categories?
				if ( $cat == 'default' ) {
					$breadcrumb = $crumb;
				} elseif ( in_array( $cat, $categories ) ) {
					$breadcrumb = $crumb;
					break;
				}
			}
		}

		return $this->normalizeParameters( $breadcrumb, DELIM, 3 );
	}

	/**
	 * Loads and preprocesses the template page
	 *
	 * @global User $wgUser
	 * @global Parser $wgParser
	 * @param string $titleText
	 * @return string
	 */
	function loadTemplate( $titleText ) {
		global $wgUser, $wgParser;

		$title = Title::newFromText( $titleText );
		$template = $this->getPageText( $title );
		if ( $template ) {
			# Drop leading and trailing blanks and escape delimiter before parsing
			# Substitute a few skin-related variables before parsing
			$template = preg_replace( '/(^\s+|\s+$)/m', '', $template );
			$template = str_replace( DELIM . DELIM . DELIM, "\x07", $template );
			$template = preg_replace_callback(
				'/' . DELIM . DELIM . '(.*?)' . DELIM . DELIM . '/', array( __CLASS__, 'translate_variable' ),
				$template
			);

			# Use the parser preprocessor to evaluate conditionals in the template
			# Copy the parser to make sure we don't trash the parser state too much
			$lparse = clone $wgParser;
			$template = $lparse->parse( $template, $title, ParserOptions::newFromUser( $wgUser ) );
			$template = str_replace( '&nbsp;', ' ', $template->getText() );
			return $template;
		}

		return '';
	}

	/**
	 * Normalize a delimited parameter line: trim leading and trailing blanks,
	 * restore escaped delimiter characters, add null elements until all optional
	 * parameters are accounted for, and drop extra parameters
	 *
	 * @param string $input
	 * @param string $delimiter
	 * @param int $count
	 * @return string
	 */
	function normalizeParameters( $input, $delimiter, $count ) {
		# Split the parameters into an array
		$params = explode( $delimiter, $input );
		$output = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$output[] = str_replace(
				"\x07", $delimiter, ($i < count( $params )) ? trim( $params[$i] ) : ''  );
		}
		return $output;
	}

	/**
	 * Returns HTML text for the specified pseudo-variable
	 *
	 * @global Parser $wgParser
	 * @global User $wgUser
	 * @param array $matches
	 * @return string
	 */
	function translate_variable( $matches ) {
		$tag = $matches[1];
		global $wgParser, $wgUser;

		switch ( strtoupper( $tag ) ) {
			case 'USERGROUPS': // @@USERGROUPS@@ pseudo-variable: Groups this user belongs to
				if ( !is_null( $wgParser->mOutput ) ) {
					$wgParser->disableCache(); // Mark this content as uncacheable
				}
				return implode( ",", $wgUser->getGroups() );

			case 'USERID':  // @@USERID@@ pseudo-variable: User Name, blank if anonymous
				if ( !is_null( $wgParser->mOutput ) ) {
					$wgParser->disableCache(); // Mark this content as uncacheable
				}
				# getName() returns IP for anonymous users, so check if logged in first
				return $wgUser->isLoggedIn() ? $wgUser->getName() : '';
			case 'FIRSTCATEGORY':
				return $this->firstCategoryInPage;
		}
	}

	/**
	 * Gets the text contents of a page with the passed-in Title object.
	 * Code graciously provided by Semantic Forms.
	 *
	 * @param Title $title
	 * @return string|null
	 */
	function getPageText( Title $title ) {
		$wikiPage = new WikiPage( $title );
		$content = $wikiPage->getContent();

		if ( $content !== null ) {
			return $content->getNativeData();
		} else {
			return null;
		}
	}

	public function getSidebarText() {
		return $this->sidebarText;
	}

	public function getLogoPath() {
		return $this->logoPath;
	}
}
