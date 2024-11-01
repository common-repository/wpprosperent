/**
 * Handle: WP Prosperent Frontend
 * Version: 1.2.3
 * Deps: jquery
 * Enqueue: true
 * 
 * Copyright (c) 2010 WPProsperent.com
 *
 * This file is part of WP Prosperent Plugin
 */

;jQuery(function($) {
	
	/*
	 * Classic template: "More Info" button
	 */
	$('ul.classic div.wpp-product').each(function() {
		var $product = $(this);
		$('<button/>')
			.addClass('wpp-toggle-info')
			.text('More Info')
			.appendTo($product)
			.bind('click', function(event) {
				event.preventDefault();
				$product.closest('li')
					.find('div.wpp-image, div.wpp-product')
						.hide()
						.end()
					.find('div.wpp-more-info')
						.slideToggle();
			});
	});
	
	
	/*
	 * Pagination
	 */
	$('ul.wpp-pages li a').bind('click', function(event) {
		event.preventDefault();
		var $this = $(this);
		$this.closest('ul.wpp-pages').siblings('form.wpp-change-page')
			.find('input[name="wpp_page"]')
				.val($this.attr('rel'))
				.end()
			.trigger('submit');
	});
	
});