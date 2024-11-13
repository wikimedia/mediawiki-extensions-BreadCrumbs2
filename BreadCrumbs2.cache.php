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
 * @author Youri van den Bogert (https://www.archixl.nl)
 * @license CC-BY-3.0
 */

class BreadCrumbs2Cache {

	/**
	 * @var null|BagOStuff
	 */
	private $cache = null;

	public function getCache() {
		if ( $this->cache === null ) {
			$this->cache = ObjectCache::getInstance( $GLOBALS['wgMainCacheType'] );
		}
		return $this->cache;
	}

	public function getCacheKey() {
		return $this->getCache()->makeKey( 'BreadCrumbs2', 'Template' );
	}

	public function set( string $template ) {
		$this->getCache()->set( $this->getCacheKey(), $template );
	}

	public function get() {
		return $this->getCache()->get( $this->getCacheKey() );
	}

}
