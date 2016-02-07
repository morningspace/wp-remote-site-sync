$j=jQuery.noConflict();

var delEvent=false;

function testSite(id) {
	if ( document.getElementById("wprss_host_"+id) && 
			document.getElementById("wprss_username_"+id) && 
			document.getElementById("wprss_pwd_"+id) ) {
		loadP();
		var h = document.getElementById("wprss_host_" + id).value; 
		var u = document.getElementById("wprss_username_" + id).value; 
		var p = document.getElementById("wprss_pwd_" + id).value; 
		if ( h != "" && u != "" && p != "" && testWp(h, u, p) ) {
			alert("Connection to " + h + " is working.");
			$j('#overlay').remove();
			return true;
		} else {
			alert("Connection to " + h + " failed.");
			$j('#overlay').remove();
			return false;
		}
	}
	return false;
}

function testWp(host, u, p) {
	var connection = {
		url : host + "/xmlrpc.php",
		username : u,
		password : p
	};
	var wp = new WordPress(connection.url, connection.username, connection.password);
	var object = wp.getPosts(1);
    if (object.faultString) {
		return false;
	} else {
		return true;
	}
}

function wprssValidateForm(n) {
	var rs = false;
	if ( delEvent == true ) {
		rs = true;
	} else {
		loadP();
		$j('.required').each(function() {
			if ($j(this).val() == '') { 
				$j(this).addClass('highlight');
			}
		});
		if ($j('.required').hasClass('highlight')) {
			alert("Please fill all fields");
			$j('#overlay').remove();
			rs = false;
		}
	}
	if ( rs == true ) {
		document.forms["wprssform"].submit();
	}
	else {
		$j('.required').each(function() {
			if ($j(this).val() == '') { 
				$j(this).removeClass('highlight');
			}
		});
	}
	return;
} 

function loadP() {
	// add the overlay with loading image to the page
	var over = '<div id="overlay">' +
		'<img id="loading" src="'+document.getElementById("wprss_plugin_path").value+'">' +
		'</div>';
	$j(over).appendTo('body');

	// hit escape to close the overlay
	$j(document).keyup(function(e) {
		if (e.which === 27) {
			$j('#overlay').remove();
		}
	});
}