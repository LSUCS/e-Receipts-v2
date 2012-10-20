var email = "";

$(document).ready(function() {

	//User dropdown
    if ($("#userbox span").html() != 'Sign In') {
        $("#userbox").mouseenter(function() {
            email = $("#userbox span").html();
            var width = $(this).width();
            $("#userbox img").attr('src', "images/logout.png");
            $("#userbox span").html('Log Out');
            $(this).css('width', width + 'px');
        });
        $("#userbox").mouseleave(function(e) {
            $("#userbox img").attr('src', "images/user.png");
            $("#userbox span").html(email);
        });
    }
          
    //Buttons
    $("button").button();
    
    //Error boxes
    $(".error-box").each(function() { makeError($(this)); });
    
    //Overlay
    Overlay.initialiseOverlay();

});

//Error function
function makeError(obj) {
    var text = obj.html();
    obj.addClass('ui-widget');
    obj.html('<div class="ui-state-error ui-corner-all"><p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span><strong>Alert:</strong> ' + text + '</p></div>');
}

//Overlay object
var Overlay = {
    
    initialiseOverlay: function() {
        $(window).resize(function() { Overlay.resizeScreen(); });
        $(document).scroll(function() { Overlay.resizeScreen(); });
        $("#close-overlay").live("click", function() { Overlay.closeOverlay(); });
    },
    openOverlay: function(showButton, text, timeout) {

        if (showButton) this.showCloseButton();
        else this.hideCloseButton();
        
        if (text.length > 0) {
            $("#overlay").removeClass().addClass("notice-overlay");
            $("#overlay-content").html(text);
        }
        else $("#overlay").removeClass().addClass("content-overlay");
        
        this.resizeScreen();
        $("#screen").fadeIn("300");
        $("#overlay").fadeIn("300");
        
        if (timeout > 0) {
            setTimeout(function () { Overlay.closeOverlay(); }, timeout);
        }
        
    },
    closeOverlay: function() {
        $("#screen").fadeOut("300");
        $("#overlay").fadeOut("300");
    },
    adjustOverlay: function() {
        $("#overlay").css('margin-top', - $("#overlay").height()/2 -50);
        $("#overlay").css('margin-left', - ($("#overlay").width()/2 + 75));
    },
    resizeScreen: function() {
        $("#screen").css('width', $(document).width());
        $("#screen").css('height', $(document).height());
        this.adjustOverlay();
    },
    hideCloseButton: function() {
        $("#close-overlay").css('display', 'none');
    },
    showCloseButton: function() {
        $("#close-overlay").css('display', 'block');
    }

}

//Curency formatter
function currencyFormatted(amount) {
	var i = parseFloat(amount);
	if(isNaN(i)) { i = 0.00; }
	var minus = '';
	if(i < 0) { minus = '-'; }
	i = Math.abs(i);
	i = parseInt((i + .005) * 100);
	i = i / 100;
	s = new String(i);
	if(s.indexOf('.') < 0) { s += '.00'; }
	if(s.indexOf('.') == (s.length - 2)) { s += '0'; }
	s = minus + s;
	return s;
}