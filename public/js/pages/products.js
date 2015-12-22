var productTable;

$(document).ready(function () {

    updateProductTable();
        
    $("#add-button").click(function() {
		$.post(
        	"index.php?page=products&action=add",
        	{ product_id: "", name: $("#product-name").val(), price: $("#product-price").val(), available: $("#product-available").is(":checked"), event: $("#event option:selected").val() },
        	function(data) {
        		if (data != null && data.error ) {
        			Overlay.openOverlay(true, data.error);
        			return;
        		}
        		Overlay.openOverlay(false, "Product Added", 1000);
                updateProductTable();
        		$("#product-name").val("Product Name");
        		$("#product-price").val("Price");
        	},
        	"json"
        );
	});
    
    $(".changeable").live('change', function() {
        var rowPos = productTable.fnGetPosition(this);
        var rowData = productTable.fnGetData(rowPos[0]);
        editProduct(rowData[0], rowData[1], rowData[2], $(this).find('input').is(":checked"), rowData[4]);
        $(this).find('input').attr('checked', !$(this).find('input').attr('checked'));
    });
    
});

function updateProductTable() {
	$("#products").html('<table id="product-table"></table>');
	$.getJSON(
			"index.php?page=products&action=get",
			function (data) {
				productTable = $("#product-table").dataTable( {
					"bJQueryUI": true,
					"sPaginationType": "full_numbers",
					"aaData": data,
					"aaSorting": [[ 0, "desc" ]],
					"aLengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
					"bAutoWidth": false,
                    "iDisplayLength": 10,
                    "sDom": 'Rf<"H"lr>t<"F"ip>',
					"aoColumns": [
						{ "sTitle": "ID", "sWidth": "60px", "sClass": "idcell" },
						{ "sTitle": "Product", "bSearchable": true, "sClass": "editable" },
						{ "sTitle": "Price", "sWidth": "100px", "sClass": "editable" },
						{ "sTitle": "Available", "sWidth": "40px", "sClass": "changeable" },
                        { "sTitle": "Event", "sClass": "selectable" },
                        { "sTitle": "Sold" }
					] } );
				
				$('.editable', productTable.fnGetNodes()).editable(
					function(value, settings) { return makeEditable(this, value); },
					{ 
					    type    : 'text',
					    onblur  : 'submit',
					    height  : '20px',
					    width   : '80%',
					    tooltip : 'Click to edit...'
					}
				);
				$('.selectable', productTable.fnGetNodes()).editable(
					function(value, settings) { return makeEditable(this, settings.data[value]); },
					{ 
					    type    : 'select',
                        data    : $("#event option").map(function() { return $(this).html(); }).get(),
					    onblur  : 'submit',
					    height  : '35px',
					    width   : '100%',
					    tooltip : 'Click to edit...'
					}
				);
				$('.changeable', productTable.fnGetNodes()).each(function() {
                    var val = $(this).html();
                    var rand = Math.floor(Math.random() * 100000);
                    $(this).html('<p><input type="checkbox" id="' + rand + '" ' + (val > 0?'checked="' + val + '" ':'') + '/><label for="' + rand + '"></label></p>');
                });
				
			});
}

function makeEditable(obj, value) {
	var rowPos = productTable.fnGetPosition(obj);
    var rowData = productTable.fnGetData(rowPos[0]);
    var old = rowData[rowPos[1]];
    rowData[rowPos[1]] = value;
    editProduct(rowData[0], rowData[1], rowData[2], rowData[3], rowData[4]);
    return(old);
}

function editProduct(id, name, price, available, event) {
    $.post(
    	"index.php?page=products&action=edit",
    	{ id: id, name: name, price: price, available: available, event: event },
    	function(data) {
            if (data != null && data.error ) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            Overlay.openOverlay(false, "Product edited", 1000);
            updateProductTable();
    	},
    	"json"
    );
}