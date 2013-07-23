/**
 * persistant variable set
 * @param array language_strings an array of type Language_string, the Language_string object holds all important data of the LANG strings
 * @param array taged_strings is a collection of all strings which have pseudo tags, used for checkup in the submision proces
 * @param int first_shown marks the first shown element of the translation submision form
 * @param int last_shown marks the last shown element of the translation submision form
 */
var language_strings = new Array();
var taged_strings;
var first_shown = 0;
var last_shown = 6;

$(document).ready(function()
{
    //will extract markup from the strings
    re_render_html();
    additional_purge();
    add_autocomplete_to_language_input();

    //check if the language is already selected if it is the selection form will not be shown
    var language = '';
    language = getCookie('selected_language');
    if (language != '' && language != null) {
        hide_language_input();
        get_original_language_values();
    }

    //handles the language selection
    $('#translator_form').submit(function(event)
    {
        event.preventDefault();
        document.cookie = 'selected_language = ' + $('#translator_language').val();
        get_original_language_values();
        hide_language_input();

    });

    //handles submition of translations
    $('#translator').on('submit', '#translator_form_submission', function(event)
    {
        event.preventDefault();
        translator_form_submit();
    });

});

/*############################################################################
 ## The next part of the script handles the initial re rendering of the page ##
 ## Including extracting the LANG strings, removing markups etc              ##
 #############################################################################*/



/*
 * retrieves the HTML of the current page
 * removes all identifiers the plugin made for strings
 * returns the clean html to the browser
 */
function re_render_html()
{
    var html = $("html").html();

    while (html.indexOf('_-start_') > -1) {

        var start_point = html.indexOf("_-start_") + 8;

        var end_point = html.indexOf("_-end_");

        //extracting the original LANG string and the metadata
        var data = html.substring(start_point, end_point);
        var new_object = new Language_string(data)

        add_to_language_array(new_object);

        data = "_-start_" + data + "_-end_";
        while (html.indexOf(data) > -1) {
            html = remove_identificators(html, data, false, new_object);
        }
    }
    document.documentElement.innerHTML = html;
}

/* Creates a object from the data passed by the LANG arrays
 *@param data string the extracted data from the rendered page
 */
function Language_string(data)
{
    this.array_name = extract_array_name(data);
    this.array_index = extract_array_index(data);
    this.string = extract_string(data);
    this.metadata = metadata(this.array_name, this.array_index);

    function extract_array_name(data)
    {
        var start_point = data.indexOf("array__") + 7;
        var end_point = data.indexOf("index__");
        var array_name = data.substring(start_point, end_point);
        return array_name;
    }
    ;

    function extract_array_index(data)
    {
        var start_point = data.indexOf("index__") + 7;
        var end_point = data.indexOf("||", data.indexOf("||") + 2);
        var array_index = data.substring(start_point, end_point);
        return array_index;
    }
    ;

    function extract_string(data)
    {
        var start_point = data.indexOf("||", data.indexOf("||") + 2) + 2;
        var string = data.substring(start_point, data.length);
        return string;
    }
    ;

    function metadata(array_name, array_index)
    {
        var meta = "array_" + array_name + "index_" + array_index;
        meta = meta.replace('$', '');
        return meta;
    }

    this.equals = function equals(other_language_string)
    {
        if ((this.array_name == other_language_string.array_name) && (this.array_index == other_language_string.array_index))
            return true;
        else
            return false;
    };

}


//Make sure every element is unique before adding it to the array
function add_to_language_array(element)
{
    for (var i = 0; i < language_strings.length; i++) {
        if (language_strings[i].equals(element)) {
            return;
        }
    }
    language_strings.push(element);
}

/**
 *removes identificators from the html, if appropriate adds <span>
 *@param string html the html of the current page
 *@param string data the extracted data part
 *@param boolean isFirst true if first occurence of the string
 *@param object new_object the object created from the data parameter
 *@return returns the purged html
 */

function remove_identificators(html, data, isFirst, new_object)
{
    //need the offset to make sure that the new <span> is not added to html tags such as <title>
    var offset = 50;
    if (html.indexOf(data) < 50) {
        offset = html.indexOf(data);
    }
    var test_string = html.substring(html.indexOf(data) - offset, html.indexOf(data) + data.length);

    var isTag = true;
    var flags = ["<title>", "value=", "title=", "alt=", "onclick="];
    for (var i = 0; i < flags.length; i++) {
        if (test_string.indexOf(flags[i]) > 0)
            isTag = false;
    }

    if (isFirst == true) {

        if (isTag) {
            html = html.replace("_-start_", "<span class='" + new_object.metadata + "'>");
            html = html.replace("_-end_", "</span>");
        } else {
            html = html.replace("_-start_", "");
            html = html.replace("_-end_", "");
        }

    } else {

        if (isTag)
            html = html.replace(data, "<span class='" + new_object.metadata + "'>" + new_object.string + "</span>");
        else
            html = html.replace(data, new_object.string);
    }

    return html;
}

/**
 *In case there has been a oversee in removing identificators
 * this function will take care of them
 */
function additional_purge()
{
    for (var i = 0; i < language_strings.length; i++) {
        if (language_strings[i].string.indexOf("_-start_") > 0) {
            language_strings[i].string = language_strings[i].string.substring(0, language_strings[i].string.indexOf("_-start_"));
        }
    }
}

/*
 * Gets list of available languages for translation via AJAX call
 * and uses jQueryUI to create autocomplete option for the language selection input
 */
function add_autocomplete_to_language_input()
{
    var r_url = get_base_url();
    r_url += "/get_languages.php";

    var ajaxRequest = $.ajax({
        url: r_url
    });

    ajaxRequest.done(function(response, textStatus, jqKHR)
    {
        var languages_object = JSON.parse(response);
        var languages_name = [];
        for (var key in languages_object) {
            if (languages_object.hasOwnProperty(key)) {
                languages_name.push(languages_object[key]);
            }
        }

        $("#translator_language").autocomplete({
            source: languages_name
        });
    });
}


/*##############################################################################
 ## The next part of the script handles picking and re-picking of the language ##
 ## Creating the translation <form> - Geting values from the database such as  ##
 ## strings, pseudo tags, votes                                                ##
 #############################################################################*/

/*if a language for translation if picked by the user
 * the form is hidden */
function hide_language_input()
{
    $('#change_language').prepend("<label id='selected_language'>"
            + " Selected language: <a class='change_selected' href='javascript:void(0)' onclick='show_language_input()'> (change) </a> " + "<span class='selected'>" + getCookie('language') + "</span> </label> ");
    $('#translator_form').addClass('hidden');
}

/* if the user wants to change the picked language
 * this function will show the translation form again and reset navigation variables
 */
function show_language_input()
{
    $('#translator_form').val('');
    $('#translator_form').removeClass('hidden');
    $('#submission_form').html('');

    first_shown = 0;
    last_shown = 5;

    $('#selected_language').remove();
}
/* Sends a AJAX request to get formated LANG strings
 * and the acctual translation form
 */
function get_original_language_values()
{
    var r_url = get_base_url();
    r_url += "get_original_language_values.php";
    var json_ob = JSON.stringify(language_strings);

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {objects: json_ob, language: getCookie('language')},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR)
    {
        var response_object = JSON.parse(response);
        var form = response_object['form'];

        language_strings = response_object['language_array'];
        taged_strings = response_object['taged_strings'];
        show_progress_bar(response_object['translated']);

        if ($('#translator_form').size() > 0)
            $('#submission_form').append(form);
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown)
    {
        var error = "<div class='error' > There has been an error retrieving the data.";
        error += "If this persists contact the site admin or <a href='mailto: b.ttalic@gmail.com?Subject=Translator%20Plugin%20Error'>b.ttalic</a></div>";
        $('#submission_form').append(error);

    });
}

/*Will show a graphical representation of the amount of translated
 * strings to the current language */
function show_progress_bar(translated)
{
    translated = parseFloat(translated).toFixed(2);
    var not_translated = parseFloat(100 - translated).toFixed(2);
    $('.translator .progress_bar #translated').width(translated + '%').html(translated + '%');
    $('.translator .progress_bar #not_translated').width(not_translated + '%').html(not_translated + '%');
}



/*##############################################################################
 ## The next part of the script handles submiting of translations, voting and  ##
 ## highlighting strings on the page. It contains navigation functions as well ##
 #############################################################################*/

/*The call is handled via AJAX
 * after the response is sent faulty inputs, if any will be marked as such
 * the user is notified about successfully saved inputs, those input boxes will be removed
 */
function translator_form_submit()
{
    var r_url = get_base_url();
    r_url += "submit_translation.php";

    var ajaxRequest = $.ajax({
        url: r_url,
        data: $('#translator_form_submission').serialize() + '&taged_strings=' + JSON.stringify(taged_strings) + '&count=' + language_strings.length,
        type: "post"
    });

    ajaxRequest.done(function(response, textStatus, jqXHR)
    {
        var response_object = JSON.parse(response);
        var bad_inputs = response_object['bad_input'];
        var good_inputs = response_object['good_input'];
        var translated = response_object['translated'];

        mark_bad_inputs(bad_inputs);
        remove_submited(good_inputs);


    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown)
    {
        console.log(textStatus);
    });
}


/*Adds a css class to faulty inputs so they are easiy recognisible, Gives a previev of those at the begining of the form
 * * @param array bad_inputs array of numbers marking the input id of the faulty inputs
 */
function mark_bad_inputs(bad_inputs)
{
    var error_message = "<ul> You forgot the &lttag&gt/&ltvar&gt in following translations:";
    for (var i = 0; i < bad_inputs.length; i++) {
        $('#translator_form_submission #input_span_' + bad_inputs[i]).addClass('bad_input');
        error_message += "<li>" + language_strings[bad_inputs[i]].string + "</li>";
    }
    error_message += '</ul>';
    if (bad_inputs.length > 0)
        $('#submision_error').html(error_message);
}

/** removes successfully submited inputs
 * @param array good_inputs array of numbers marking the input id of the successfully saved inputs
 */
function remove_submited(good_inputs)
{
    for (var i = 0; i < good_inputs.length; i++) {
        $('#translator_form_submission #input_span_' + good_inputs[i]).remove();
    }
    if (good_inputs.length > 0)
        $('#submision_success').html("Successfully submited: " + good_inputs.length + " translation(s)!</br> Thank You!");

}


/** A request is sent to mark the vote in the database
 * if the translation is deleted because of too many bad votes (currently 5) the translation is deleted and the page reloaded
 * othervise the object which made the request is highlighted and disabled
 * @param int sign the vote -1 or 1 depending on user choice
 * @param string id the id of the language_string associated with the string which is voted
 * @param object object the object which made the call
 */
function vote(sign, id, object)
{
    r_url = get_base_url();
    r_url += "vote.php";

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {sign: sign, translation_id: language_strings[id].translation_id},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR)
    {
        var response_object = JSON.parse(response);
        if (response_object['refresh']) {
            location.reload();
        } else {
            var pair = '';
            if (object.id.indexOf('down') >= 0) {
                pair = '#' + object.id.replace('down', 'up');
            }
            else
                pair = '#' + object.id.replace('up', 'down');
            $(object).attr('disabled', '');
            $(pair).removeAttr('disabled');
        }
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown)
    {
        console.log(textStatus);
    });
}

/*Will show the next 6 (or less) input fields of the translation form
 If neccessary disables the arrow for showing next translations
 */
function show_next()
{
    var count = 0;

    $('#input_span_' + last_shown).nextAll('span[id^=input_span_]').slice(0, 6).each(function()
    {
        $(this).removeClass('temp_hidden');
        count++;

    });
    if (count < 6)
        $('#down_img').addClass('hidden');


    $('#input_span_' + last_shown).prevAll('span[id^=input_span_]').slice(0, 6).each(function()
    {
        $(this).addClass('temp_hidden');
    });
    last_shown += count;
    first_shown += count;
    $('#up_img').removeClass('hidden');
}

/*Will show the previous 6 (or less) input fields of the translation form
 If neccessary disables the arrow for showing previous translations
 */
function show_previous() {
    var count = 0;

    $('#input_span_' + first_shown).nextAll('span[id^=input_span_]').slice(0, 6).each(function()
    {
        $(this).addClass('temp_hidden');
        count++;

    });

    $('#input_span_' + first_shown).prevAll('span[id^=input_span_]').slice(0, 6).each(function()
    {
        $(this).removeClass('temp_hidden');
    });
    last_shown -= count;
    first_shown -= count;
    if (first_shown <= 0)
        $('#up_img').addClass('hidden');

    $('#down_img').removeClass('hidden');
}



/**
 * adds CSS class to highligh selected string(s) on page
 */
function highlight()
{
    var id = event.target.id;
    id = id.replace('_image', '');
    var value = $('#' + id + "_hidden").val();

    var class_name = '.' + value;
    $(class_name).each(function() {
        $(this).addClass('translator_highlighted');
    });
}
/**
 * removes CSS class of highlighted string(s) on page
 */
function remove_highlight()
{
    var id = event.target.id;
    id = id.replace('_image', '');
    var value = $('#' + id + "_hidden").val();

    var class_name = '.' + value;
    $(class_name).each(function() {
        $(this).removeClass('translator_highlighted');
    });
}



/*##############################################################################
 ## The next part of the script is a set of helper functions used by the       ##
 ## above code                                                                 ##
 #############################################################################*/


/* Creates the base url for AJAX calls and resource retreival (e.g. images) */
function get_base_url()
{
    var r_url = window.location.pathname;
    r_url = r_url.substring(0, r_url.indexOf("public_html") + 11); //r_url.substring(0, r_url.indexOf("public_html")); 
    r_url += "/crowdtranslator/"; //"plugins/crowdtranslator/"; 

    return r_url;
}

/*Gets the script saved cookie
 http://www.w3schools.com/js/js_cookies.asp
 */
function getCookie(c_name)
{
    var c_value = document.cookie;
    var c_start = c_value.indexOf(" " + c_name + "=");
    if (c_start == -1)
    {
        c_start = c_value.indexOf(c_name + "=");
    }
    if (c_start == -1)
    {
        c_value = null;
    }
    else
    {
        c_start = c_value.indexOf("=", c_start) + 1;
        var c_end = c_value.indexOf(";", c_start);
        if (c_end == -1)
        {
            c_end = c_value.length;
        }
        c_value = unescape(c_value.substring(c_start, c_end));
    }
    return c_value;
}


/*Shows guidelines for CrowdTranslator usage*/
function show_guidelines()
{

    var translator = $('#translator');
    var r_url = get_base_url();
    r_url += '/images/'
    var close_url = r_url + 'close.png';
    var highlight_url = r_url + 'highlight.png';
    var remove_highlight_url = r_url + 'rhighlight.png';
    var vote_up = r_url + 'vote_up.png';
    var vote_down = r_url + 'vote_down.png';
    var up = r_url + 'up.png';
    var down = r_url + 'down.png';

    var display = "<div id='translator_guidelines' style='height:" + translator.height() + "px; width:" + translator.width() + "px; top:" + translator.position().top + "px'> ";
    display += "<span > <img src= '" + close_url + "' onclick='hide_guidelines()' class='form_image'/>";
    display += "<div id='translator_guidelines_inner'>";
    display += "<ul>";
    display += "<li> Click <img src= '" + highlight_url + "'/> To <span class='translator_highlighted'>highlight</span> the string on the page. </li>";
    display += "<li> Highlight will not work if the string is inside title, value etc tags </li> ";
    display += "<li> Click <img src= '" + remove_highlight_url + "' /> To remove highlight from strings on the page. </li>";
    display += "<li> Click <img src= '" + vote_up + "' /> To vote up a translation you think is good. </li>";
    display += "<li> Click <img src= '" + vote_down + "' /> To vote down a translation you think is bad. </li>";
    display += "<li> If a string has &lttag&gt or &ltvar&gt in it writte them in an appropriate place in the translation </li>";
    display += "<li> Click <img src= '" + up + "'/> To show next inputs </li>";
    display += "<li> Click <img src= '" + down + "'/> To show previous inputs </li>";

    display += "</ul>";
    display += "</div>";
    display += "</div>";
    $('#translator').append(display);

}
/* Hides guidlines for translator usage */
function hide_guidelines()
{
    $('#translator_guidelines').remove();
}

/**
 * Shows content of hidden divs
 * @param string id the id of the function calles
 */
function show(id)
{
    var element = $('#' + id + '_content');
    if (element.hasClass('hidden')) {
        element.removeClass('hidden');
        $('#' + id).html('(hide)');
    } else {
        element.addClass('hidden');
        $('#' + id).html('(show)');
    }

}
/**
 * Issues AJAX call to retrieve next/previous translations, change order of translations or increase translations display per page
 * @param int limit number of translations per page
 * @param int start first translation to be shown
 * @param int admin indicading if admin mode or user mode
 * @param string order_by indicating the ordering of the table
 */
function show_more_translations(limit, start, admin, order_by)
{

    var limit_input = $('#limit').val();

    if (!isNaN(limit_input)) {
        limit = limit_input;
        strat = -1;
    }

    if (limit == 0)
        limit = null;

    console.log(order_by);

    r_url = get_base_url();
    r_url += "lib-translator.php";

    var function_name = 'get_user_translations_table';
    if (admin == 1)
        function_name = 'get_translations_table';

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {function: function_name, limit: limit, start: start, order_by: order_by},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR) {
        $('#user_translations').html(response);
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown) {
        console.log(textStatus);
    });

}

/**
 * Issuing AJAX call to delete a translation from the database
 * @param int id The translation id
 * @param string translation the translation text
 */
function delete_translation(id, translation)
{

    r_url = get_base_url();
    r_url += "lib-translator.php";

    var confirmation = confirm("Are you sure you want to delete '" + translation + "' ?");

    if (!confirmation) {
        return;
    }

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {function: 'delete_translation', id: id},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR) {
        $('#translation_' + id).remove();
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown) {
        console.log(textStatus);
    });

}

/**
 * Issues AJAX call to display all available badges
 * @param int admin Indicating if showing all badges awarded to a user or all badges awailable
 */
function get_all_badges(admin)
{

    var link_value = $('#badges_show').html();
    if (link_value == '(show all)') {

        r_url = get_base_url();
        r_url += "lib-translator.php";
        console.log('get all');
        var ajaxRequest = $.ajax({
            url: r_url,
            data: {function: 'get_user_badges', admin: admin},
            type: "POST"
        });

        ajaxRequest.done(function(response, textStatus, jqKHR)
        {
            console.log(response);
            $('#badges_display').html(response);
            $('#badges_show').html('(hide)');
        });

        ajaxRequest.fail(function(jqXHR, textStatus, errorThrown)
        {
            console.log(textStatus);
        });

    } else {
        $('#badges_show').html('(show all)');
        $('#badges_display').children().each(function(index)
        {
            if (index > 3)
                $(this).remove();
        });
    }

}

/**
 * Changing the number of translations shown per page
 * @see show_more_translations
 */
function translation_table_change_limit()
{
    var limit = $('#limit').val();
    var script = $('#show_next').attr("onclick");
    script = script.substring(script.indexOf('('));
    var params = script.split(',');
    console.log(params);
    var admin = parseInt(params[2]);
    var order_by = params[3];
    order_by = order_by.replace(')', '');
    show_more_translations(limit, -1, admin, order_by);
}

/**
 * Issuing AJAX call to put a user on the block list
 */
function block_user(user_id)
{
    r_url = get_base_url();
    r_url += "lib-translator.php";

    var confirmation = confirm("Are you sure you want to block this user from translating ? Blocking a user will also delete all of his translations");

    if (!confirmation) {
        return;
    }

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {function: 'block_user', user_id: user_id},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR) {
        location.reload();
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown) {
        console.log(textStatus);
    });

}

/**
 * Issuing AJAX call to remove a user from the block list
 */
function remove_block(user_id)
{
    r_url = get_base_url();
    r_url += "lib-translator.php";

    var confirmation = confirm("Are you sure you want to un-block this user?");

    if (!confirmation) {
        return;
    }

    var ajaxRequest = $.ajax({
        url: r_url,
        data: {function: 'remove_block', user_id: user_id},
        type: "POST"
    });

    ajaxRequest.done(function(response, textStatus, jqKHR) {
        location.reload();
    });

    ajaxRequest.fail(function(jqXHR, textStatus, errorThrown) {
        console.log(textStatus);
    });

}