var eventTable;

$(document).ready(function () {

    updateEventTable();
        
    $("#add-button").click(function() {
		$.post(
        	"index.php?page=events&action=add",
        	{ name: $("#event-name").val(), ticket_limit: $("#ticket-limit").val() },
        	function(data) {
        		if (data != null && data.error) {
        			Overlay.openOverlay(true, data.error);
        			return;
        		}
        		Overlay.openOverlay(false, "Product Added", 1000);
                updateEventTable();
        		$("#event-name").val("Event Name");
        		$("#ticket-limit").val("Ticket Limit");
        	},
        	"json"
        );
	});
    
});

function updateEventTable() {
	$("#events").html('<table id="event-table"></table>');
	$.getJSON(
			"index.php?page=events&action=get",
			function (data) {
				eventTable = $("#event-table").dataTable( {
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
						{ "sTitle": "Name", "bSearchable": true, "sClass": "editable" },
						{ "sTitle": "Ticket Limit", "sClass": "editable" },
                        { "sTitle": "Tickets Sold" }
					] } );
				
				$('.editable', eventTable.fnGetNodes()).editable(
					function(value, settings) { return makeEditable(this, value); },
					{ 
					    type    : 'text',
					    onblur  : 'submit',
					    height  : '35px',
					    width   : '100%',
					    tooltip : 'Click to edit...'
					}
				);
				
			});
}

function makeEditable(obj, value) {
	var rowPos = eventTable.fnGetPosition(obj);
    var rowData = eventTable.fnGetData(rowPos[0]);
    var old = rowData[rowPos[1]];
    rowData[rowPos[1]] = value;
    editProduct(rowData[0], rowData[1], rowData[2]);
    return(old);
}

function editProduct(id, name, ticket_limit) {
    $.post(
    	"index.php?page=events&action=edit",
    	{ id: id, name: name, ticket_limit: ticket_limit },
    	function(data) {
            if (data != null && data.error) {
                Overlay.openOverlay(true, data.error);
                return;
            }
            Overlay.openOverlay(false, "Event edited", 1000);
            updateEventTable();
    	},
    	"json"
    );
}