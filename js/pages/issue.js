var odd = true;
var autofilled = false;

$(document).ready(function() {
    
    //Add product
    $("#add-product").click(function() {
        var sel = $("#products option:selected");
        if ($("#selected-products").html().indexOf("No products added") != -1) $("#selected-products").html("");
        $("#selected-products").append('<div class="product ' + (odd?"odd":"even") + '" product="' + sel.attr('id').replace('product', '') + '" value="' + sel.attr('value') + '">' + sel.text() + '<span class="delete-product"></span></div>');
        odd = !odd;
        updateTotal();
    });
    
    //Delete product
    $(".delete-product").live('click', function() {
        $(this).parent().remove();
        odd = true;
        $(".product").each(function() {
            $(this).removeClass('odd');
            $(this).removeClass('even');
            $(this).addClass((odd?"odd":"even"));
            odd = !odd;
        });
        if ($(".product").length == 0) $("#selected-products").html("No products added");
        updateTotal();
    });
    
    //Comment thing
	$("#comments").click(function() {
		if ($(this).val() == "Comments") {
			$(this).val("");
			$(this).css("color", "#000");
		}
	});
	$("#comments").blur(function() {
		if ($(this).val() == "") {
			$(this).val("Comments");
			$(this).css("color", "#999");
		}
	});
    
    //Submit receipt
    $("#issue-receipt").click(function() { submitReceipt(); });
    
    //Autocomplete
    $("#name").autocomplete({
        source: "index.php?page=issue&action=search&type=name",
        minLength: 2,
        select: function(event, ui) { autoFill("name", ui.item.value); }
    });
    $("#email").autocomplete({
        source: "index.php?page=issue&action=search&type=email",
        minLength: 2,
        select: function(event, ui) { autoFill("email", ui.item.value); }
    });
    $("#studentid").autocomplete({
        source: "index.php?page=issue&action=search&type=studentid",
        minLength: 2,
        select: function(event, ui) { autoFill("student_id", ui.item.value); }
    });
    $("#forum").autocomplete({
        source: "index.php?page=issue&action=search&type=forum",
        minLength: 2,
        select: function(event, ui) { autoFill("customer_forum_name", ui.item.value); }
    });
    
    updateTotal();
    updateProducts();
    
});

function updateProducts() {
    $.get(
        "index.php?page=issue&action=products",
        function (data) {
            $("#products").html("");
            for (var i = 0; i < data.length; i++) {
                $("#products").append('<option id="product' + data[i].product_id + '" value="' + data[i].price + '">' + data[i].product_name + ' - £' + data[i].price + '</option>');
            }
        },
        'json');
}

function autoFill(type, value) {
    if (autofilled) return;
    $.post(
        "index.php?page=issue&action=autofill",
        { type: type, value: value },
        function (data) {
            if (data == null) return;
            $("#name").val(data.name);
            $("#email").val(data.email);
            $("#studentid").val(data.student_id);
            $("#forum").val(data.customer_forum_name);
            autofilled = true;
        },
        'json');
}

function submitReceipt() {

    Overlay.openOverlay(false, '<img src="images/loading.gif" />');

    var products = new Array();
    $(".product").each(function() {
        products.push($(this).attr('product'));
    });
    if ($("#comments").val() == "Comments") var comments = "";
    else var comments = $("#comments").val();
    
    $.post(
        "index.php?page=issue&action=submit",
        { email: $("#email").val(), name: $("#name").val(), student_id: $("#studentid").val(), forum: $("#forum").val(), comments: comments, products: products.join(",") },
        function(data) {
        
            if (data != null && data.error) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            
            Overlay.openOverlay(false, "Receipt Issued", 1500);
            
            updateProducts();
            $("#email").val("");
            $("#name").val("");
            $("#studentid").val("");
            $("#forum").val("");
            $("#comments").val("Comments");
            $("#comments").css("color", "#999");
            $("#selected-products").empty();
            
            odd = true;
            autofilled = false;
            
            updateTotal();
        },
        "json"
    );
    
}

function updateTotal() {
	 var total = 0;
     $("#selected-products .product").each(function() {
        total += parseFloat($(this).attr('value'));
     });
     $("#total span").html("&pound;" + currencyFormatted(total));
}
