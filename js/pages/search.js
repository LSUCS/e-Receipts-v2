var receiptTable = "";
var loadedId = 0;

$(document).ready(function() {

    //Datepickers
    $("#start-date, #end-date").datepicker({ dateFormat: "yy-mm-dd", onSelect: function () { if ($("#bydate").is(":checked")) search(); } });
    
    //Bind search events
    $("#search-value, #product, #bydate, #refunded, #search-type, #event").bind('change keyup select', function() { search(); });
    
    //Search value autocomplete
    $("#search-value").autocomplete({
        source: "index.php?page=issue&action=search&type=" + $("#search-type option:selected").val(),
        select: function(event, ui) { $("#search-value").val(ui.item.value); search() },
        minLength: 1,
    });
    $("#search-type").change(function() {
        $("#search-value").autocomplete({
            source: "index.php?page=issue&action=search&type=" + $("#search-type option:selected").val(),
            select: function(event, ui) { $("#search-value").val(ui.item.value); search() },
            minLength: 1,
        });
    });
    
    //Product autocomplete
    $("#product").autocomplete({
        source: "index.php?page=issue&action=search&type=product",
        select: function(event, ui) { $("#search-value").val(ui.item.value); search(); },
        minLength: 1,
    });
    
    //Row highlighting
    $("#receipt-table tbody tr").live('mouseover', function() {
        $(this).find('td').addClass("row-hover");
    });
    $("#search-results tbody tr").live('mouseleave', function() {
        $(this).find('td').removeClass("row-hover");
    });
    
    //Row clicking
    $("#receipt-table tbody tr").live('click', function() {
        loadReceipt($(this).find("td").first().html());
    });
    
    //Delete button
    $("#delete-receipt").live('click', function() {
        Overlay.openOverlay(false, 'Are you sure you wish to delete this receipt?<br /> <button id="confirm-delete">Yes</button><button id="abort-delete">No</button>');
        $("#confirm-delete, #abort-delete").button();
    });
    $("#abort-delete").live('click', function() {
        loadReceipt(loadedId);
    });
    $("#confirm-delete").live('click', function() {
        deleteReceipt(loadedId);
    });
    
    search();

});

function deleteReceipt(id) {
    Overlay.openOverlay(false, '<img src="images/loading.gif" />');
    $.post(
        "index.php?page=search&action=delete",
        { id: id },
        function (data) {
        
            if (data != null && data.error) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            
            Overlay.openOverlay(false, "Receipt deleted", 1500);
            search();
        
        },
        'json');
}

function loadReceipt(id) {
    Overlay.openOverlay(false, '<img src="images/loading.gif" />');
    $.post(
        "index.php?page=search&action=getreceipt",
        { id: id },
        function (data) {
        
            if (data != null && data.error) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            
            loadedId = data.receipt_id;
        
            //Fill values
            $(".receipt-id").html(data.receipt_id);
            $(".receipt-date").html(data.date);
            $(".receipt-name").html(data.name);
            $(".receipt-email").html(data.email);
            $(".receipt-forum").html(data.customer_forum_name);
            $(".receipt-studentid").html(data.student_id);
            $(".receipt-issuer").html(data.issuer_forum_name);
            $(".receipt-comments").html(data.comments);
            
            //Product table
            $(".products-body").html("");
            for (var i = 0; i < data.purchases.length; i++) {
                var purchase = data.purchases[i];
                $(".products-body").append('<div class="product-row ' + (i % 2?"odd":"even") + '"><span class="product-name">' + purchase.product_name + '</span><span class="product-price">£' + purchase.price + '</span><span class="product-refunded">' + purchase.refunded.replace(1,"Yes").replace(0,"No") + '</span></div>');
            }
            $(".product-row:last").addClass("last-product");
                    
            $("#overlay-content").html($("#view-receipt").html());
            
            //Buttons
            $("#overlay-content .controls").html('<a href="index.php?page=refund&id=' + data.receipt_id + '"><button id="issue-refund">Issue Refund</button></a><button id="delete-receipt" value="' + data.receipt_id + '">Delete Receipt</button>');
            $(".controls button").button();
            
            Overlay.openOverlay(true, "");
        
        },
        'json');
}

function search() {

    $.post(
        "index.php?page=search&action=search",
        { search_type: $("#search-type option:selected").val(),
          search_value: $("#search-value").val(),
          product: $("#product").val(),
          event: $("#event option:selected").val(),
          refunded: $("#refunded").is(":checked"),
          by_date: $("#bydate").is(":checked"),
          start_date: $("#start-date").val(),
          end_date: $("#end-date").val()
        },
        function (data) {
            $("#search-results").html('<table id="receipt-table"></table>');
            receiptTable = $("#receipt-table").dataTable( {
                "bJQueryUI": true,
                "sPaginationType": "full_numbers",
                "aaData": data,
                "aLengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "bAutoWidth": false,
                "iDisplayLength": 10,
                "sDom": 'Rf<"H"lrT>t<"F"ip>',
                "aaSorting": [[ 0, "desc" ]],
                "aoColumns": [
                    { "sTitle": "ID", "sWidth": "50px", "sClass": "idcell" },
                    { "sTitle": "Date", "sWidth": "80px" },
                    { "sTitle": "Name" },
                    { "sTitle": "Email" },
                    { "sTitle": "Issuer" },
                    { "sTitle": "Products", "sWidth": "200px" }
                ] } );
        },
        'json');

}