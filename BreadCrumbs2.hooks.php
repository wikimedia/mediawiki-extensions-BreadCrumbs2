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
use HtmlFormatter\HtmlFormatter;

class BreadCrumbs2Hooks {

	/**
	 * Main hook
	 *
	 * @global boolean $wgBreadCrumbs2HideUnmatched
	 * @global boolean $wgBreadCrumbs2RemoveBasePageLink
	 * @global string $wgLogo
	 * @global string $wgScriptPath
	 * @param Skin $skin
	 * @param QuickTemplate $template
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec(
	Skin &$skin, QuickTemplate &$template ) {
		global $wgBreadCrumbs2HideUnmatched, $wgBreadCrumbs2RemoveBasePageLink;

		# Only show breadcrumbs when viewing the page, not editing, etc.
		# The following line should perhaps utilize Action::getActionName( $skin->getContext() );
		if ( $skin->getRequest()->getVal( 'action', 'view' ) != 'view' ) {
			return true;
		}
		# Get the list of categories for the current page
		$categories = $skin->getOutput()->getCategories();
		$title = $skin->getRelevantTitle();

		$breadCrumbs2 = new BreadCrumbs2( $categories, $title );
		if ( $wgBreadCrumbs2HideUnmatched && !$breadCrumbs2->hasBreadCrumbs() ) {
			// If no breadcrumbs are defined for this page, do nothing.
			return true;
		}

		$currentSubtitle = $template->get( 'subtitle' );

		if ( $title->isSubpage() && $wgBreadCrumbs2RemoveBasePageLink && $breadCrumbs2->hasBreadCrumbs() ) {
			// If breadcrumbs are defined for this page, then
			// remove elements in the "subpages" class, which are links back to the base page.
			$htmlFormatter = new HtmlFormatter( $currentSubtitle );
			$subTitleDoc = $htmlFormatter->getDoc();
			$a = new DOMXPath( $subTitleDoc );
			// In MW1.25 and earlier, there is only one span with class subpages only, but we'll use a
			// fancy query so it's more future proof.
			$nodes = $a->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' subpages ')]" );
			foreach ( $nodes as $node ) {
				$node->parentNode->removeChild( $node );
			}
			$currentSubtitle = $subTitleDoc->saveHTML();
		}

		$template->set( 'subtitle', $breadCrumbs2->getOutput() . $currentSubtitle );

		$sidebarText = $breadCrumbs2->getSidebarText();
		if ( $sidebarText != '' ) {
			# See if there's a corresponding link in the sidebar and mark it as active.
			# This is especially useful for skins that display the sidebar as a tab bar.
			if ( method_exists( $template, 'setActiveSidebarLink' ) ) {
				# The DynamicSkin extension can build the tabs (sidebar) dynamically,
				# and not necessarily from $template->data['sidebar'], so DynamicSkin
				# and derived skins have a setActiveSidebarLink() function
				$template->setActiveSidebarLink( $sidebarText );
			} else {
				# Normal skins use the global sidebar data
				foreach ( $template->data['sidebar'] as $bar => $cont ) {
					foreach ( $cont as $key => $val ) {
						if ( $val['text'] == $sidebarText ) {
							$template->data['sidebar'][$bar][$key]['active'] = true;
							break;
						}
					}
				}
			}
		}

		# Finally, see if we should change the site logo
		# Don't go overboard with this... subtle is better.
		$logoPath = $breadCrumbs2->getLogoPath();
		if ( $logoPath != '' ) {
			global $wgLogo, $wgScriptPath;
			// FIXME: Does not work with modern MediaWiki versions and modern skins, which have already
			// set the logo at this point using the ResourceLoader
			$wgLogo = $wgScriptPath . '/' . $logoPath;
		}

		return true;
	}
}
