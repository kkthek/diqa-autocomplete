( function ( $, mw ) {

	/**
	 * Modify this function to add additional data in auto-completion
	 */
	var showExtraAutocompleteHints = function(extraData) {
		
		var span = $('<span>')
			.addClass('diqa-ac-hint');
		
		if (extraData && extraData.category && extraData.category != '') {
			span.append(" ["+extraData.category+"]");
		}
		
		return span.html();
			
	};

/* extending jQuery functions for custom highlighting */
window.XPF = window.XPF || {};
window.XPF.autocompleteRenderItem = function( ul, item) {

	var delim = this.element.context.delimiter;
	var term;
	if ( delim === null ) {
		term = this.term;
	} else {
		term = this.term.split( delim ).pop();
	}
	var re = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" + term.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") + ")(?![^<>]*>)(?![^&;]+;)", "gi");
	var loc = item.label.search(re);
	var t;
	if (loc >= 0) {
		t = item.label.substr(0, loc) + '<strong>' + item.label.substr(loc, term.length) + '</strong>' + item.label.substr(loc + term.length);
	} else {
		t = item.label;
	}
	return $( "<li></li>" )
		.data( "item.autocomplete", item )
		.append( " <a>" + t + " "+showExtraAutocompleteHints(item.data)+"</a>" )
		.appendTo( ul );
};

window.XPF.select2FormatResult = function(value, container, query) {
	var term = query.term;
	var text = value.text;
	var image = value.image;
	var description = showExtraAutocompleteHints(value.data);
	var markup = "";

	var text_highlight = pf.select2.base.prototype.textHighlight;
	if ( text !== undefined && image !== undefined && description !== undefined ) {
		markup += "<table class='sf-select2-result'> <tr>";
		markup += "<td class='sf-result-thumbnail'><img src='" + image + "'/></td>";
		markup += "<td class='sf-result-info'><div class='sf-result-title'>" + text_highlight(text, term) + "</div>";
		markup += "<div class='sf-result-description'>" + description + "</div>";
		markup += "</td></tr></table>";
	} else if ( text !== undefined && image !== undefined ) {
		markup += "<img class='sf-icon' src='"+ image +"'/>" + text_highlight(text, term);
	} else if ( text !== undefined && description !== undefined ) {
		markup += "<table class='sf-select2-result'> <tr>";
		markup += "<td class='sf-result-info'><div class='sf-result-title'>" + text_highlight(text, term) + "</div>";
		markup += "<div class='sf-result-description'>" + description + "</div>";
		markup += "</td></tr></table>";
	} else {
		markup += text_highlight(text, term);
	}

	return markup;
};

}( jQuery, mediaWiki ) );