function populate_comp_overall(compid){
    $(document).ready(function() {
        $('#comp_name').text('Calculating Results ...');
        $.ajax({
            type: "POST",
            url: '/_get_comp_result/'+compid,
            contentType:"application/json",
            dataType: "json",
            success: function (json) {
                var taskNum = json.stats.valid_tasks
                console.log('taskNum='+taskNum);
                var columns = [];
                json.classes.forEach( function(item, index){
                    if (index == 0) {
                        columns.push({data: 'ranks.rank', title:'#'});
                    }
                    else {
                        columns.push({data: 'ranks.class'+index.toString(), title:'#', defaultContent: '', visible: false});
                    }
                });
                columns.push({data: 'fai_id', title:'FAI', defaultContent: '', visible: false});
                columns.push({data: 'civl_id', title:'CIVL', defaultContent: '', visible: false});
                columns.push({data: 'name', title:'Name'});
                columns.push({data: 'nat', title:'NAT', defaultContent: '', visible: false});
                columns.push({data: 'sex', title:'Sex', defaultContent: '', visible: false});
                columns.push({data: 'glider', title:'Glider', defaultContent: ''});
                columns.push({data: 'glider_cert', title:'Class', defaultContent: '', visible: false});
                columns.push({data: 'sponsor', title:'Sponsor', defaultContent: ''});
                columns.push({data: 'score', title:'Total'});
                json.tasks.forEach( function(item, index) {
                    var code = item.task_code
                    columns.push({data: 'results.'+index.toString(), title: code, defaultContent: ''});
                });
                $('#results_table').DataTable( {
                    data: json.data,
                    paging: false,
                    searching: true,
                    saveState: true,
                    info: false,
                    dom: 'lrtip',
                    columns: columns,
                    rowId: function(data) {
                            return 'id_' + data.par_id;
                    },
                    initComplete: function(settings) {
                        var table = $('#results_table');
                        var rows = $("tr", table).length-1;
                        // Get number of all columns
                        var numCols = table.DataTable().columns().nodes().length;
                        console.log('numCols='+numCols);

                        // comp info
                        $('#comp_name').text(json.info.comp_name);
                        $('#comp_date').text(json.info.date_from + ' - ' + json.info.date_to);
                        if (json.info.comp_class != "PG") {
                            update_classes(json.info.comp_class);
                        }

                        // some GAP parameters
                        $('#formula tbody').append(
                                    "<tr><td>Director</td><td>" + json.info.MD_name + '</td></tr>' +
                                    "<tr><td>Location</td><td>" + json.info.comp_site + '</td></tr>' +
                                    "<tr><td>Formula</td><td>" + json.formula.formula_name + '</td></tr>' +
                                    "<tr><td>Overall Scoring</td><td>" + json.formula.overall_validity + ' (' + json.formula.validity_param + ')</td></tr>');
                        if (json.formula.overall_validity == 'ftv') {
                            $('#formula tbody').append(
                                    "<tr><td>Total Validity</td><td>" + json.stats.total_validity + '</td></tr>');
                        }

                        // remove empty cols
                        for ( var i=1; i<numCols; i++ ) {
                            var empty = true;
                            table.DataTable().column(i).data().each( function (e, i) {
                                if (e != "") {
                                    empty = false;
                                    return false;
                                }
                            } );

                            if (empty) {
                                table.DataTable().column( i ).visible( false );
                            }
                        }
                        // class picker
                        $("#dhv option").remove(); // Remove all <option> child tags.
                        // at the moment we provide the highest EN rating for a class and the overall_class_filter.js uses this.
                        // if we want to be more specific and pass a list of all EN ratings inside a class we can do something like this: https://stackoverflow.com/questions/15759863/get-array-values-from-an-option-select-with-javascript-to-populate-text-fields
                        $.each(json.classes, function(index, item) {
                            $("#dhv").append(
                                $("<option></option>")
                                    .text(item.name)
                                    .val(item.limit)
                            );
                        });
                    }
                });
            }
        });
    });
}