/**
 * Handle: WP Prosperent Shortcode Constructor
 * Version: 1.2.3
 * Deps: jquery
 * Enqueue: true
 * 
 * Copyright (c) 2010 WPProsperent.com
 *
 * This file is part of WP Prosperent Plugin
 */

;jQuery(function($) {
	
	var identifier = 'wpProsperentShortcode';
	var $container = $('#' + identifier);
	
	if (!$container.length)	{
		return;
	}
	
	/*
	 * Multiple templates
	 */
	var $templates = $container.find('#wpProsperentShortcodeTemplates');
	
	$templates.find('a.add-template').bind('click', function(event) {
		event.preventDefault();
		$templates.find('li.pattern')
			.clone()
			.removeClass('pattern')
			.appendTo($templates)
			.hide()
			.fadeIn();
	});
	
	$templates.find('a.remove-template').live('click', function(event) {
		event.preventDefault();
		$(this).closest('li.template').remove();
	});
	
	$templates.find('input.number').live('change', function(event) {
		var $this = $(this);
		var value = parseInt($this.val());
		if (isNaN(value) || value < 1) {
			value = 1;
		}
		$this.val(value);
	});
	
	
	/*
	 * Submit button
	 */
	$container.find('p.submit input[type=button]').bind('click', function(event) {
		event.preventDefault();
		
		var attributes = '';
		var $multiTemplates = $templates.find('li.template').not('li.pattern');
		
		$container.find('table.form-table td').not($templates).find(':input').each(function() {
			var $this = $(this);
			
			var name = $this.attr('name');
			name = name.substring(identifier.length + 1, name.length - 1);
			
			var value = $.trim($this.val().replace(/"/g, '{quot}').replace(/</g, '&lt;').replace(/>/g, '&gt;'));
			if (value != '' && $this.is('.integer')) {
				value = parseInt(value);
				if (isNaN(value)) {
					value = 1;
				}
			}
			
			// Custom logic in case "Multiple templates" option is used
			if (name == 'template') {
				// Override value
				if ($multiTemplates.length) {
					name = 'templates';
					value = '';
					$multiTemplates.each(function() {
						var $this = $(this);
						var template = $.trim($this.find('select').val());
						var items = $.trim($this.find('input.number').val());
						
						if (value != '') {
							value += '|';
						}
						value += template + ':' + items; 
					});
				}
			}
			
			if (value != '' && value != 'default') {
				attributes += ' ' + name + '="' + value + '"';
			}
		});
		
		send_to_editor('[wpp' + attributes + '] ');
	});
	
});