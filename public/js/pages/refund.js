$(document).ready(function() {

    //Comment thing
	$("#refund-comments").click(function() {
		if ($(this).val() == "Comments") {
			$(this).val("");
			$(this).css("color", "#000");
		}
	});
	$("#refund-comments").blur(function() {
		if ($(this).val() == "") {
			$(this).val("Comments");
			$(this).css("color", "#999");
		}
	});
    
    $(".product-row input").live('change', function() { calculateTotal(); });
    
    $("#submit-refund").click(function() { issueRefund(); });
    
    calculateTotal();
    
});

function calculateTotal() {
    var total = 0;
    $(".product-row").each(function() {
        if ($(this).find('input').is(":checked")) {
            total += parseFloat($(this).find('.product-price').html().replace("£", ""));
        }
    });
    $("#total").html("£" + currencyFormatted(total));
}

function issueRefund() {
    Overlay.openOverlay(false, '<img src="images/loading.gif" />');
    var purchases = new Array();
    $(".product-row input:checked").each(function() {
        purchases[purchases.length] = $(this).attr('id').replace("purchase","");
    });
    $.post(
        "index.php?page=refund&action=submit",
        { purchases: purchases, id: $("#receipt_id").html(), comments: $("#refund-comments").val() },
        function (data) {
            if (data != null && data.error) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            Overlay.openOverlay(false, "Refund Issued", 1500);
            setTimeout('window.location.href="index.php?page=search"', 1500);
        },
        'json');
}