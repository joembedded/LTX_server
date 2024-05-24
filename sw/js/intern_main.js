/***************************************
 * intern_main.js Scripts (C) JoEmbedded.de 
 * Tipp: Debug: add ?xxx as param to script
 * include for reload.
 *****************************************/
/* eslint-disable no-undef */
/* eslint-disable no-unused-vars */
/* eslint-disable no-mixed-spaces-and-tabs */

"use strict";

// ------- Globals --------------
var prgVersion = "V0.65 (24.05.2024)";
var prgName = "LTX - MicroCloud" + prgVersion;
var prgShortName = "LTX";

var mapUrl = "https://maps.google.com/maps?f=q";
var modalCloseRedir; // Redirect-Link for AlertBox

var userName; // username as String
var userRole = 0;
var userID = 0;

var globalAlarmCnt = -1;
var globalErrCnt = -1;
var globalWarnCnt = -1;

var userAnzDevices; // Number of User's devices
var deviceWList = []; // Source Data from Worker
var deviceXList = []; // extra Data Viewer (HTML etc)
var lastSeenTimestamp = 0; // Parameter 'last': Last DB-Time
var lastSyncTimestamp = 0; // Parameter 'last': Last SYNCED

var deltaLastSync; // Delta to last Server Sync in secs
var spinnerVisible = false; // For modal spinner

var autoTimerResync = 10000; // x msec
var autoTimerLastSync;
var autoID = 0; // Counts each OK poll
var onlineStatus = 0;
var ajaxActiveFlag = 0;
var msgActiveFlag = 0; // 1 Bit for each Window Type

var msgCloseTimeoutHandler; // Close-Handler
var lastExpand = false; // Global Fold/Unfold

var userData; // User 'user' Data after Callback
var addDevTyp; // 0: OWN 1: Guest Device
var editDeviceData; // Device Data after Callback

// Parameter Edit Variables
var editDeviceParam; // Device Parameter after Callback (Array)
var editOrgDeviceParam; // Device Parameter after Callback (Array, Orignal)
var editMAC; // MAC of current device
var editName; // Name of current device
var editIdx; // Index in Data 
var editIndexChan0; // Index "@0" (by Design >=19)
var editAktChan; // Actual ChannelNo
var editAktIdx; // Actual Index of Editet Channel in editDeviceParam.
var editSCookie; // Plain Date instead of Unix
var editParPending; // set to >0 if pending
var editInfo; // device_info.dat as array
var infoObj; // Object
var cellObj; // Cellinfo of edited Device
var logId;
var logLstart;
var logLanz;
var logRaw; // Raw lines as received from Log
var logPageSize = 20; // Pagesize Log

var clearIdx; // Zu loeschendes Device

// --- Lists -------------

//--- Options for UserRoles (use in combination with generate/eval_checks()
//e.g generate_checks("divRole0",cklUserRoles, variable , cklMaskRoles, 0);
//    variable=eval_checks("divRole0");

var cklUserRoles = [ // Place for Options 
	{
		typ: "Reset Alarms"
	},
	{
		typ: "Reset Warnings"
	},
	{
		typ: "Reset Errors"
	},
	{
		typ: "Start New Measure"
	},
	{
		typ: "16"
	},
	{
		typ: "32"
	},
	{
		typ: "64"
	},
	{
		typ: "128"
	},
	{
		typ: "Access 'Details'"
	},
	{
		typ: "Access 'Hardware'"
	},
	{
		typ: "Access 'Server'"
	},
];
var cklMaskRoles = 0x70F; // Invisibles

var cklHKFlags = [{
		typ: "HK Battery"
	},
	{
		typ: "HK Temperature"
	},
	{
		typ: "HK Humidity"
	},
	{
		typ: "HK Percent"
	},
	{
		typ: "HK Baro"
	},
];

var cklActionFlags = [{
		typ: "Record ON"
	},
	{
		typ: "Value from Cache",
		class: "jo-parsec"
	}, // secondary Parameter
	{
		typ: "Check Alarms"
	},
];

var cklRecFlags = [{
		typ: "Record ON"
	},
	{
		typ: "Ring Memory (recommended)",
		class: "jo-parsec"
	},
];

var optEdBatt = [{
		opt: "Not watched"
	},
	{
		opt: "&lt 25%"
	},
	{
		opt: "&lt 50%"
	},
];

var optNetModes = [{
		opt: "Net OFF (!)"
	},
	{
		opt: "ON only for Transfer"
	},
	{
		opt: "Smart ON/OFF"
	},
	{
		opt: "Stay ONLINE"
	},
];

var optErrorPolicy = [{
		opt: "Off (No Retries)"
	},
	{
		opt: "Retries for ALARMS)"
	},
	{
		opt: "Retries for All"
	},
];

// --- Functions -----------
/* Generate a Checkbox-Field */
function generate_checks(tdiv, tsrc, tist, tmask, tdis) {
	var cbs = "";
	var tm = 1;
	for (var i = 0; i < tsrc.length; i++) {
		if (tmask & tm) {
			cbs += "<span";
			if (tsrc[i].class !== undefined) cbs += " class='" + tsrc[i].class + "'";
			cbs += "><input class='w3-check' type='checkbox' value='" + tm + "'";
			if (tist & tm) cbs += " checked";
			if (tdis & tm) cbs += " disabled";
			cbs += ">" + tsrc[i].typ + "<br></span>";
		}
		tm <<= 1;
	}
	document.getElementById(tdiv).innerHTML = cbs;
}

function eval_checks(tdiv) { // returns number
	var checkedCbs = document.querySelectorAll('#' + tdiv + ' input[type="checkbox"]:checked');
	var sum = 0;
	for (var i = 0; i < checkedCbs.length; i++) {
		sum += parseInt(checkedCbs[i].value);
	}
	//console.log("SUM:"+sum);
	return sum;
}
/* Generate Drops-Field */
function generate_drops(tdiv, tsrc, tist) {
	var sel = document.getElementById(tdiv);
	var cbs = "";
	for (var i = 0; i < tsrc.length; i++) {
		cbs += "<option value='" + i + "'>" + tsrc[i].opt + "</option>";
	}
	sel.innerHTML = cbs;
	sel.selectedIndex = tist;
}

function eval_drops(tdiv) { // return number
	var sel = document.getElementById(tdiv);
	var idx = sel.selectedIndex;
	var opt = sel[idx].value;
	//console.log("OPT:"+opt);
	return opt;
}

function showSecondary(temp) {
	var shspar = document.getElementById("showSecPar").checked;
	if (temp == undefined) temp = 400;
	if (shspar) $(".jo-parsec").show(temp);
	else $(".jo-parsec").hide(temp);
}

// Self removing ListItem added in loglist
// Bsp.: log_info('Achtung, irgendwas ist schiefgegangen irgendwas ist schiefgegangenirgendwas ist schiefgegangen','w3-red')
function log_info(txt, color = "w3-white") {
	var now = new Date().toLocaleTimeString();

	$("#loglist").append('<li class="w3-display-container ' + color + '" style="display:none">' + now + ": " + txt +
		'<span onclick="$(this).parent().remove();" class="w3-button w3-display-topright w3-light-gray"><b>X</b></span></li>');
	$("#loglist li:last-child").slideDown(150);
}

// Show Modal Boxes
function modal_show(modalFormName) {
	switch (modalFormName) {
		case "modalAlert":
			msgActiveFlag |= 1;
			break;
		case "modalAddMAC":
			msgActiveFlag |= 2;
			break;
		case "modalEditParameter":
			msgActiveFlag |= 4;
			break;
		case "modalEditDevice":
			msgActiveFlag |= 8;
			break;
		case "modalEditUser":
			msgActiveFlag |= 16;
			break;
		case "modalEditInfo":
			msgActiveFlag |= 32;
			break;
		case "modalRemoveMAC":
			msgActiveFlag |= 64;
			break;
		case "modalClearDevice":
			msgActiveFlag |= 128;
			break;
		case "modalShowWEA":
			msgActiveFlag |= 256;
			break;
		default:
			alert("Form? " + modalFormName);
	}
	$("#" + modalFormName).fadeIn(50);
}

function modal_close(modalFormName) {
	$("#" + modalFormName).fadeOut(200);
	switch (modalFormName) {
		case "modalAlert":
			msgActiveFlag &= ~1;
			ajaxActiveFlag = 0;
			break;
		case "modalAddMAC":
			msgActiveFlag &= ~2;
			break;
		case "modalEditParameter":
			msgActiveFlag &= ~4;
			break;
		case "modalEditDevice":
			msgActiveFlag &= ~8;
			break;
		case "modalEditUser":
			msgActiveFlag &= ~16;
			break;
		case "modalEditInfo":
			msgActiveFlag &= ~32;
			break;
		case "modalRemoveMAC":
			msgActiveFlag &= ~64;
			break;
		case "modalClearDevice":
			msgActiveFlag &= ~128;
			break;
		case "modalShowWEA":
			msgActiveFlag &= ~256;
			break;
	}
	if (modalCloseRedir !== undefined) {
		window.location.assign(modalCloseRedir);
	}
}

function ownAlertAutoClose() {
	if (msgCloseTimeoutHandler !== undefined) {
		clearTimeout(msgCloseTimeoutHandler);
	}
	msgCloseTimeoutHandler = undefined;
	modal_close("modalAlert");
}

/* Own AlertBox with optional link (if link set). Optional Autoclose after x secs pass*/
function ownAlert(title, text, link, timeout) {
	if (text == undefined) {
		text = title;
		title = "INFO:";
	}
	modalCloseRedir = link;
	var cont = "<h2>" + title + "</h2>" + text;
	$("#alertContent").html(cont);
	modal_show("modalAlert");
	if (timeout !== undefined) {
		msgCloseTimeoutHandler = setTimeout(ownAlertAutoClose, timeout * 1000); //AutClose for Info
	}
	var logtext = text.replace(/<[^>]+>/g, '');
	log_info(title + " " + logtext, "w3-yellow");
}

function w3_open() {
	var mySidebar = document.getElementById("mySidebar");
	if (mySidebar.style.display === 'block') {
		mySidebar.style.display = 'none';
	} else {
		mySidebar.style.display = 'block';
	}
}

function w3_close() {
	var mySidebar = document.getElementById("mySidebar");
	mySidebar.style.display = "none"; // Egtl. nur zu wenn Close sichtbar?
}

// Remove A Device (Own/Guest)
function removeDevice() {
	modal_show("modalRemoveMAC");
}

function removeSubmit(event) {
	var mac = $("#rMAC").val().toUpperCase();

	//console.log(mac);
	// for(var i=0;i<deviceWList.length;i++){	if(mac==deviceWList[i].mac)	fnd=true; }

	event.preventDefault();
	modal_close("modalRemoveMAC");

	lastSeenTimestamp = 0; // Get ALL
	user_poll({
		cmd: "removeDevice",
		oldmac: mac
	});
	document.getElementById("modalSpinner").style.display = "block";
	spinnerVisible = true;
}


// Add A Device to user's List: ShowDialog() and Submit()
function addDevice(adtyp) {
	addDevTyp = adtyp;
	var typtxt;
	if (addDevTyp) typtxt = "Guest";
	else typtxt = "own";
	document.getElementById("addType").innerText = typtxt;
	modal_show("modalAddMAC");
}

function addSubmit(event) { // Submit Form AddDevice
	var mac = $("#aMAC").val();
	var owTok = $("#aOwnerToken").val();
	// console.log(mac);
	// console.log(owTok);
	event.preventDefault();
	lastSeenTimestamp = 0; // Get ALL
	if (!addDevTyp) user_poll({
		cmd: "addDevice",
		newmac: mac,
		newtok: owTok
	});
	else user_poll({
		cmd: "addGuestDevice",
		newmac: mac,
		newtok: owTok
	});
	modal_close("modalAddMAC");
	document.getElementById("modalSpinner").style.display = "block";
	spinnerVisible = true;
}

function deltaToTimeString(delta) { // Helper Func
	var h, lstxt = "";
	if (delta >= 86400) {
		h = Math.floor(delta / 86400);
		delta -= 86400 * h;
		lstxt += h + "d";
	}
	h = Math.floor(delta / 3600);
	delta -= 3600 * h;
	if (h < 10) lstxt += "0";
	lstxt += h + "h";
	h = Math.floor(delta / 60);
	delta -= 60 * h;
	if (h < 10) lstxt += "0";
	lstxt += h + "m";
	if (delta < 10) lstxt += "0";
	lstxt += delta + "s";
	return lstxt;
}
// Calculate Age String of given Date (from DB);
function getAgeStr(ls, towarn, toerr, idx) {
	if (isNaN(ls)) return "(never)";
	if (deltaLastSync == undefined) return "(unknown)";
	var delta = deltaLastSync + lastSeenTimestamp - ls; // Database Time - last seeb in Unix-Secs
	var destr = deltaToTimeString(delta);
	if (delta < 0 || delta > 87000) destr = "<span class='w3-red'>" + destr + "</span>";
	else if (delta < 300) destr = "<span class='w3-green'>" + destr + "</span>";
	delta /= 3600
	if (toerr && delta > toerr) deviceXList[idx].content.style.background = 'red';
	else if (towarn && delta > towarn) deviceXList[idx].content.style.background = 'yellow';
	else deviceXList[idx].content.style.background = 'white';

	return destr;
}

// For All devices and Alarms
function showAges() {

	if (deltaLastSync == undefined || deltaLastSync > 31536000) return; // 1y
	document.getElementById("sync").innerText = deltaLastSync + "s";

	// For ALL devices
	var alarm_cnt = 0;
	var err_cnt = 0;
	var warn_cnt = 0;

	for (var i = 0; i < userAnzDevices; i++) {
		// Device Info Line
		var adev = deviceWList[i];
		var cont = getAgeStr(adev.last_seen_ux, adev.timeout_warn, adev.timeout_alarm, i); // &nbsp: No Word Wrap

		alarm_cnt += adev.alarms_cnt;
		err_cnt += adev.err_cnt;
		warn_cnt += adev.warnings_cnt;

		if (deviceXList[i].detailsVisible == false) {
			cont += getBullets(adev.warnings_cnt, adev.err_cnt, adev.alarms_cnt, i);
		}
		deviceXList[i].content.innerHTML = cont;
	}

	// Total Info:
	if (globalAlarmCnt != alarm_cnt || globalErrCnt != err_cnt || globalWarnCnt != warn_cnt) {
		globalAlarmCnt = alarm_cnt;
		globalErrCnt = err_cnt;
		globalWarnCnt = warn_cnt;
		var gbm = document.getElementById("globalBell");
		if (alarm_cnt) {
			gbm.innerText = alarm_cnt;
			gbm.style.background = "magenta";
			gbm.classList.add("jo-blink");
		} else if (err_cnt) {
			gbm.innerText = err_cnt;
			gbm.style.background = "red";
			gbm.classList.add("jo-blink");
		} else if (warn_cnt) {
			gbm.innerText = warn_cnt;
			gbm.style.background = "yellow";
			gbm.classList.remove("jo-blink");
		} else {
			gbm.innerText = "0";
			gbm.style.background = "black";
			gbm.classList.remove("jo-blink");
		}
	}

}

/* Get Infos about User, Devices, .. */
function user_poll(jcmd) {

	ajaxActiveFlag = 1; // Reset on Success or Error-Close
	autoTimerLastSync = Date.now();

	if (jcmd === undefined) jcmd = {};
	//else console.log(jcmd); // <--- DEBUG enble to show CMDs
	jcmd.last = lastSeenTimestamp;

	$.post("w_php/w_main.php", jcmd, function (data) {
		if (spinnerVisible) {
			document.getElementById("modalSpinner").style.display = "none";
			spinnerVisible = false;
		}
		//console.log(data); // <- DEBUG Show incomming Data

		var res = parseInt(data.status);
		if (isNaN(res) || res != 0) {
			var rel;
			if (res <= -1000) {
				rel = "login.php";
				if (!autoID && res != -1000) { // Jump Directly Autologin (ausser DB-Error)
					window.location.assign(rel);
					return;
				}
				rel += "?a=login"; // With Login
			}
			ownAlert("ERROR:", data.status + " (" + autoID + ")", rel);
			return;
		}
		var latency = parseFloat(data.status.substr(data.status.indexOf("(") + 1));
		console.log("Latency: " + latency); // <-- LATENCY
		// --Updates--
		if(data.dbnow != undefined) lastSeenTimestamp = parseInt(data.dbnow); // UNIX Time of Database
		lastSyncTimestamp = Date.now(); // Fuer Sync
		deltaLastSync = 0;

		if (data.devices !== undefined) {
			var anzW = data.devices.length; // unsorted Data from Worker

			if (data.user_name != undefined && data.user_name != userName) { // Check User Setup
				userName = data.user_name;
				userRole = data.user_role;
				userID = data.user_id;
				var info = document.getElementById("welcomeInfo");
				if (!(userRole & 32768)) { // Demo
					info.innerHTML = "<b>&nbsp;* Limited Rights *&nbsp;</b>";
					info.style.background = 'yellow';
				} else if (userRole & 65536) { // ADMIN
					info.innerHTML = "<b>&nbsp;* Administrator Rights*&nbsp;</b>";
					info.style.background = 'red';
					$(".cadmin").css("display", "none"); // Disable Normal user items
					$(".sadmin").css("display", "block"); // Only Main User
				}

				$("#userNameNav").text(userName);
				$("#userNameTitle").text(userName);
				document.title = prgShortName + " '" + userName + "'";
			}

			if (data.anz_devices != userAnzDevices) { // Check Number of Devices
				userAnzDevices = data.anz_devices;
				if (anzW != userAnzDevices) {
					ownAlert("ERROR:", "Internal: Inconsistent Number of Devices");
					lastSeenTimestamp = 0;
					return;
				}

				var devLiParent = $("#deviceList"); // Something changed: Rebuild Device List
				devLiParent.empty();
				$("#aMAC").val("");
				$("#aOwnerToken").val("");
				$("#noOfDevices").text("(" + userAnzDevices + ")");
				//if(anzW) console.log("---- "+anzW+": Devices----"); // <- DEBUG
				var adev;
				var idx;
				for (var i = 0; i < anzW; i++) {
					//console.log(data.devices[i]);
					adev = data.devices[i];
					idx = adev.idx;
					//console.log(adev.mac); // <- DEBUG

					var hstr = "<li class='w3-display-container w3-white'>";

					var isguest = (adev.owner_id != userID); // .role always there
					if (isguest) { // Access with token
						hstr += "<div><a class='jo-mac' href='gdraw.html?s=" + adev.mac + "&lim=1000&k=" + adev.token +
							"' target='_blank'><b><i class='fas fa-chart-line w3-orange'></i> MAC: " + adev.mac;
					} else {
						hstr += "<div><a class='jo-mac' href='gdraw.html?s=" + adev.mac + "&lim=1000" +
							"' target='_blank'><b><i class='fas fa-chart-line w3-green w3-text-black'></i> MAC: " + adev.mac;
					}

					if (typeof adev.name == 'string' && adev.name.length > 0) {
						hstr += " '" + adev.name + "'";
					}
					hstr += "</b></a>";

					if (userRole & 65536) {
						if (adev.owner_id != null) hstr += " (Owner:'" + adev.real_owner_id + "')"; // Just for info
						else hstr += " (Owner: (none))";
					}
					var gpsinfo = "";

					if ((adev.units !== null && adev.units.includes(":Lat") && adev.units.includes(":Lng")) || (adev.posflags > 0)) {
						if (isguest) { // Access with token
							gpsinfo = "<br><a class='jo-mac' href='gps_view.html?s=" + adev.mac + "&lim=1000&k=" + adev.token +
								"' target='_blank'><b><i class='fas fa-map-marker-alt w3-text-orange'></i>&nbsp; Position View</b></a>";
						} else {
							gpsinfo = "<br><a class='jo-mac' href='gps_view.html?s=" + adev.mac + "&lim=1000" +
								"' target='_blank'><b><i class='fas fa-map-marker-alt w3-text-green'></i>&nbsp; Position View</b></a>";
						}
					}
					if (userRole & 65536) {
						if (gpsinfo == "") gpsinfo += "<br>"
						gpsinfo += "<a class='jo-mac' href='w_php/w_gdraw_db.php?s=" + adev.mac + "&lim=10000000&mk" +
							"' target='_blank'><b><i class='fas fa-database w3-text-blue'></i>&nbsp; Raw Data</b></a>";
						gpsinfo += "<a class='jo-mac' href='../legacy/device_lx.php?s=" + adev.mac +
							"' target='_blank'><b><i class='fas fa-file w3-text-blue'></i>&nbsp; Legacy Data</b></a>";
														
					}

					hstr += " Age:&nbsp;<span id='devLiCon" + i + "'></span>" +
						'<span onclick="macShowDetails(' + i +
						')" class="w3-button w3-display-topright w3-light-gray"><i class="fas fa-ellipsis-v"></i></span>' + gpsinfo +
						'</div><div style="display: none" id="devLiDet' +
						i + '">I</div></li>';

					devLiParent.append(hstr);
					// Add 3 Extras

					if (deviceXList[i] == undefined) deviceXList[i] = {};
					deviceXList[i].content = document.getElementById("devLiCon" + i); // Cache Content Element
					deviceXList[i].detailsVisible = false; // and Visibility
					deviceXList[i].ocnt_lines = parseInt(adev.lines_cnt); // Old Number of Count Lines
					deviceXList[i].isnew = true;
					deviceXList[i].isguest = isguest;
				}
			}
			// Update Dynamic Data
			var warn_cnt = 0; // Sane Banes as lxu_trigger.php
			var err_cnt = 0;
			var alarm_cnt = 0;

			//if(anzW) console.log("---- "+anzW+": Changes----"); // <- DEBUG
			for (i = 0; i < anzW; i++) {
				adev = data.devices[i];
				idx = adev.idx;
				//console.log(adev.mac); // <- DEBUG
				//console.log(adev);
				// Cast Data to faster formats
				adev.warnings_cnt = parseInt(adev.warnings_cnt);
				adev.err_cnt = parseInt(adev.err_cnt);
				adev.alarms_cnt = parseInt(adev.alarms_cnt);
				adev.lines_cnt = parseInt(adev.lines_cnt);
				adev.timeout_warn = parseInt(adev.timeout_warn);
				adev.timeout_alarm = parseInt(adev.timeout_alarm);
				adev.last_seen_ux = Math.floor(Date.parse(adev.last_seen + " GMT") / 1000); // DB is UTC
								

				deviceWList[idx] = adev;
				// new Lines
				var nlc = adev.lines_cnt - deviceXList[idx].ocnt_lines;
				deviceXList[idx].ocnt_lines = adev.lines_cnt;
				if (nlc) { // New Lines: Show Info
					var cont = "MAC: " + adev.mac;

					if (typeof adev.name == 'string' && adev.name.length > 0) {
						cont += " '" + adev.name + "'";
					}

					cont += ": New&nbsp;Lines&nbsp;Data:&nbsp;" + nlc;
					var ccol = "w3-white"; // Default white
					if (adev.warnings_cnt) {
						cont += ", Warnings:&nbsp;" + adev.warnings_cnt;
						ccol = "w3-yellow";
					}
					if (adev.err_cnt) {
						cont += ", Errors:&nbsp;" + adev.err_cnt;
						ccol = "w3-red";
					}
					if (adev.alarms_cnt) {
						cont += ", Alarms:&nbsp;" + adev.alarms_cnt;
						ccol = "w3-purple";
					}
					log_info(cont, ccol);
				}
				var msgs = adev.warnings_cnt + adev.err_cnt + adev.alarms_cnt;
				if (nlc || ((deviceXList[idx].isnew && msgs > 0 && !(userRole & 65535)) || userAnzDevices < 3)) {
					deviceXList[idx].isnew = false;
					if (deviceXList[idx].detailsVisible == false) macShowDetails(idx);
					else $("#devLiDet" + idx).html(generateDetails(idx)); // Just update Content
				} else $("#devLiDet" + idx).html(generateDetails(idx)); // Just update Content
			}
		}
		// Got User Data?
		if (data.user !== undefined) {
			userData = data.user;
			editUserCallback();
		} else if (data.device !== undefined) {
			editDeviceData = data.device;
			editDeviceCallback();
		} else if (data.locinfo !== undefined) {
			var cinfo;
			if (!data.locinfo.startsWith("OK")) {
				cinfo = "<span class='w3-red'>Info: '" + data.locinfo + "'</span>";
				document.getElementById("infoCellular").innerHTML = cinfo;
				/*
							}else{
								cinfo="Info: '"+data.locinfo+"'";
								document.getElementById("infoCellular").innerHTML=cinfo;
				*/
			}
			// Accuracs + TimingAdvance , save estimated Values
			var h = parseFloat(data.accuracy) + (cellObj.ta * 550 + 250);
			if (!isNaN(h)) {
				document.getElementById("infoAccuracy").value = h;
				cellObj.eRad = h; // Save
			}
			h = parseFloat(data.lat);
			if (!isNaN(h)) {
				cellObj.eLat = h.toFixed(7);
				document.getElementById("infoLat").value = cellObj.eLat;
			}
			h = parseFloat(data.lon);
			if (!isNaN(h)) {
				cellObj.eLon = h.toFixed(7);
				document.getElementById("infoLon").value = cellObj.eLon;
			}
		} else if (data.lres !== undefined) { // Logfiles
			logRaw = data.lres;
			editInfFillLog();
		} else if (data.weares !== undefined) { // Notes
			logRaw = data.weares;
			showWEAFill();
		} else if (data.dinfo !== undefined) {
			editInfo = data.dinfo;
			editInfoCallback();
		} else if (data.iparam !== undefined) {
			editDeviceParam = data.iparam; // no ROLE!
			// 1 Channel V1.0 Min 33 Lines
			if (editDeviceParam.length < 33) {
				console.log(data.iparam);
				ownAlert("ERROR:", "Parameter invalid (L:" + editDeviceParam.length + ")");
			} else if (editDeviceParam[0].startsWith("@100") == false) {
				console.log(data.iparam);
				ownAlert("ERROR:", "Parameter invalid ('#0:" + editDeviceParam[0] + ")");
			} else {
				editParPending = data.par_pending;
				editSCookie = data.scookie; // Text
				editParamCallback();
			}
		}
		autoID++;
		ajaxActiveFlag = 0;
	}, 'json');
}
// Compact Infos
function getBullets(w, e, a, idx) {
	var resh = " ";
	if (w + e + a > 0) resh += "<button class='w3-button w3-padding-small' onclick='macShowDetails(" + idx + ")'>";
	if (w > 0) resh += "<span class='w3-yellow w3-badge'>" + w + "</span>";
	if (e > 0) resh += "<span class='w3-red w3-badge jo-blink'>" + e + "</span>";
	if (a > 0) resh += "<span class='w3-purple w3-badge jo-blink'>" + a + "</span>";
	if (w + e + a > 0) resh += "</button>";
	return resh;
}

// Logger Overview
function generateDetails(idx) {
	var adev = deviceWList[idx];
	var cont = "<table>"; // Complete Content als List 

	if (adev.vals !== null) {
		var vals = adev.vals.split(" ");

		var units;
		if (adev.units != null) units = adev.units.split(" ");

		for (var ii = 0; ii < vals.length; ii++) {
			var alarmflag = 0;
			var warnflag = 0;
			var errorflag = 0;
			var kv = vals[ii].split(":");
			var kvn = parseInt(kv[0]); // ChannelNo
			var unit = "???"; // Find Unit, assume unknown
			if (!isNaN(kvn) && kvn >= 0 && kvn < 200 && kv.length == 2 && kv[1].length >= 1 && units != undefined) {
				for (var i = 0; i < units.length; i++)
					if (parseInt(units[i]) == kvn) {
						var uv = units[i].split(":");
						if (uv.length == 2) unit = uv[1];
						break; // Found And Exit
					}
			} else errorflag = 1;
			// Unit now known or not found

			var valstr = kv[1]
			if (valstr == undefined) valstr = '?'

			var cidstr
			if (kvn >= 90) cidstr = "H" + kvn + ": &nbsp;" // Look similar to BlueShell
			else cidstr = "#" + kvn + ": &nbsp;"

			if (valstr.charAt(0) == '*') { // Alarm
				valstr = valstr.substring(1);
				alarmflag++;
			}

			if (isNaN(valstr)) { //Maybe Error Message
				errorflag++;
			} else {
				var fval = parseFloat(valstr);
				var proc
				// Spezialwerte Batterie/Feuchte
				if (kvn == 90) { // **Battery Voltage**
					var ulow = adev.vbat0
					var uhigh = adev.vbat100
					if (ulow > 0 && uhigh > ulow) {
						proc = (fval - ulow) / (uhigh - ulow) * 100;
						if (proc > 100) proc = 100;
						// else if(proc<0) proc=0;	// Nega zeigen
						valstr += "(" + proc.toFixed(0) + "%)";
						if ((adev.flags & 7) == 1 && proc < 25) warnflag++; // **Voltage<25%**
						else if ((adev.flags & 7) == 2 && proc < 50) warnflag++; // **Voltage<50%**
					}
				} else if (kvn == 93) { // **Battery Capacity**
					var cbat = adev.cbat
					if (cbat > 0) {
						proc = (cbat - fval) / (cbat) * 100;
						// if(proc<0) proc=0;	// Nega zeigen
						valstr += "(" + proc.toFixed(0) + "%)";
						if ((adev.flags & 7) == 1 && proc < 25) warnflag++; // **Capacity<25%**
						else if ((adev.flags & 7) == 2 && proc < 50) warnflag++; // **Capacity<50%**
					}
				} else if (kvn == 92 && (adev.flags & 8) && fval > 80) warnflag++; // **Humidity>80%**
			}

			var icont; // Line inner Content
			if (errorflag) icont = "<tr style='background: #FF8080'>"; // light red
			else if (alarmflag) icont = "<tr style='background: #FFC0FF'>"; // light magenta
			else if (warnflag) icont = "<tr style='background: #FFFF00'>"; // yellow
			else icont = "<tr>";
			icont += "<td>" + cidstr + " </td><td>" + valstr + "</td><td>&nbsp;" + unit + "</td></tr>";

			cont += icont;
		}
	} else {
		cont += "(No Data)";
	}
	cont += "</table>";
	var lwarn = adev.warnings_cnt;
	var lerr = adev.err_cnt;
	var lalarm = adev.alarms_cnt;

	//var hdr="<span>"+adev.lines_cnt+"&nbsp;Total&nbsp;Lines&nbsp;Data</span>&nbsp;";
	var hdr = "<span>" + adev.anz_lines + "&nbsp;Lines&nbsp;Data</span>&nbsp;";

	if (lwarn) {
		if (adev.role & 2) hdr += " <button onclick='removeWarnings(" + idx + ")' class='w3-button w3-padding-small'>Warnings:<span class='w3-yellow w3-badge'>" + lwarn + "</span></button>";
		else hdr += " <span class='w3-padding-small'>Warnings:<span class='w3-yellow w3-badge'>" + lwarn + "</span></span>";
	}
	if (lerr) {
		if (adev.role & 4) hdr += " <button onclick='removeErrors(" + idx + ")' class='w3-button w3-padding-small'>Errors:<span class='w3-red w3-badge jo-blink'>" + lerr + "</span></button>";
		else hdr += " <span class='w3-padding-small'>Errors:<span class='w3-red w3-badge jo-blink'>" + lerr + "</span></span>";
	}
	if (lalarm) {
		if (adev.role & 1) hdr += " <button onclick='removeAlarms(" + idx + ")' class='w3-button w3-padding-small'>Alarms:<span class='w3-purple w3-badge jo-blink'>" + lalarm + "</span></button>";
		else hdr += " <span class='w3-padding-small'>Alarms:<span class='w3-purple w3-badge jo-blink'>" + lalarm + "</span></span>";
	}

	var footer = "";
	if (adev.lat != null && adev.lng != null) {
		var gpslink = mapUrl + "&q=" + adev.lat + "," + adev.lng + "&z=12";
		var gdate = new Date(Date.parse(adev.last_gps + " GMT")); // UTC
		footer = "<div><a href='" + gpslink + "' target='_blank'><i class='fas fa-map-marker-alt w3-text-orange'></i><b> Cell/GNSS Position from " + gdate.toLocaleString().replace(" ", "&nbsp;");
		if (adev.rad > 0) footer += ", Accuracy: " + adev.rad + "m";
		footer += "</b></a></div>";
	}

	if (deviceXList[idx].isguest) hdr += " <span class='w3-text-orange'>(Guest&nbsp;Device)</span>";

	if (adev.role & 1024) footer += "<div><button onclick='editDeviceDetails(" + idx + ")' class='w3-button w3-padding-small'><i class='fas fa-globe fa-fw w3-text-green'></i>Server</button>";
	if (adev.role & 512) footer += "<button onclick='editDeviceParameter(" + idx + ")' class='w3-button w3-padding-small'><i class='fas fa-cog fa-fw w3-text-blue'></i>Hardware</button>";
	if (adev.role & 256) footer += "<button onclick='editDeviceInfo(" + idx + ")' class='w3-button w3-padding-small'><i class='fas fa-info-circle w3-text-teal'></i> Details</button>";
	if (adev.role & (7 + 256)) footer += "<button onclick='showDeviceWEA(" + idx + ")' class='w3-button w3-padding-small'><i class='fas fa-bell fa-fw w3-text-red'></i> Notes</button>";
	if (adev.role & 8) footer += "<button onclick='clearDeviceData(" + idx + ")' class='w3-button w3-padding-small'><i class='fas fa-trash-alt fa-fw'></i> Clear</button>";

	footer += "</div>";
	return "<div class='w3-border-bottom'>" + hdr + "</div>" + cont + footer;
}

function removeWarnings(idx) {
	user_poll({
		cmd: "removeWarnings",
		mac: deviceWList[idx].mac
	});
}

function removeErrors(idx) {
	user_poll({
		cmd: "removeErrors",
		mac: deviceWList[idx].mac
	});
}

function removeAlarms(idx) {
	user_poll({
		cmd: "removeAlarms",
		mac: deviceWList[idx].mac
	});
}

function macShowDetails(idx) {
	var devInfo = $("#devLiDet" + idx);
	var adev = deviceWList[idx];
	var cont = getAgeStr(adev.last_seen_ux, adev.timeout_warn, adev.timeout_alarm, idx); // &nbsp: No Word Wrap
	if (deviceXList[idx].detailsVisible == false) {
		devInfo.html(generateDetails(idx));
		devInfo.slideDown(150);
		deviceXList[idx].detailsVisible = true;
		lastExpand = true;
	} else {
		devInfo.slideUp(150);
		devInfo.html(""); // Release Memory
		deviceXList[idx].detailsVisible = false;
		lastExpand = false;
		cont += getBullets(adev.warnings_cnt, adev.err_cnt, adev.alarms_cnt, idx);
	}
	deviceXList[idx].content.innerHTML = cont;
}

function clickBell() { // Expand all devices with alarms
	for (var i = 0; i < userAnzDevices; i++) {
		if (deviceXList[i].detailsVisible == true) continue; // already OK
		var totalm = deviceWList[i].warnings_cnt + deviceWList[i].err_cnt + deviceWList[i].alarms_cnt;
		if (totalm) macShowDetails(i); // else: change it
	}
}

function expandDevices() {
	var newShow = true;
	if (lastExpand == true) newShow = false;
	for (var i = 0; i < userAnzDevices; i++) {
		if (deviceXList[i].detailsVisible == newShow) continue; // already OK
		macShowDetails(i); // else: change it
	}
	lastExpand = newShow;
}

// Runs with ca. 1 sec
function secTickTimer() { // Alle 5 Sekunden aufgerufen
	var justNow = Date.now();
	if (navigator.onLine !== onlineStatus) {
		var han = document.getElementById("hasNet");
		onlineStatus = navigator.onLine;
		if (onlineStatus) han.style.display = 'none';
		else han.style.display = 'inline';
	}
	//console.log(msgActiveFlag);
	var delta = justNow - autoTimerLastSync;
	if (delta >= autoTimerResync && onlineStatus && ajaxActiveFlag == 0 && msgActiveFlag == 0) {
		user_poll();
	} else {
		deltaLastSync = Math.floor((Date.now() - lastSyncTimestamp) / 1000);
		showAges(); // 1nce per sec
	}
}
//--- Device Infos START ----
function editDeviceInfo(idx) {

	var h = "";
	if (deviceWList[idx].rad) h = deviceWList[idx].rad;
	document.getElementById("infoAccuracy").value = h;
	h = "";
	if (deviceWList[idx].lat) h = deviceWList[idx].lat;
	document.getElementById("infoLat").value = h;
	h = "";
	if (deviceWList[idx].lng) h = deviceWList[idx].lng;
	document.getElementById("infoLon").value = h;

	h = "";
	if (deviceWList[idx].last_gps) h = "Last updated: <b>" + deviceWList[idx].last_gps.replace(" ", "&nbsp;") + "</b>";
	document.getElementById("infoCellular").innerHTML = h;

	editMAC = deviceWList[idx].mac;
	document.getElementById("infoMAC").innerText = editMAC;
	editName = deviceWList[idx].name;
	if (editName == null) editName = "";
	editIdx = idx;
	var edname = editName;
	if (!editName.length) edname = "(undefined)";
	document.getElementById("infoDeviceName").innerText = edname;
	user_poll({
		cmd: "getInfo",
		mac: editMAC
	}); // device_info.dat
	//console.log("Get Info "+editMAC );
}


//------------- Notes -----------
function showWEAPos(dir) {
	logLstart += (dir * logPageSize);
	if (logLstart < 0) logLstart = 0;
	infoGetWEA(logLstart, logPageSize);
}

function showWEAFill() {
	var hltab = "<table class='w3-table-all'>";
	hltab += "<tr><th>Notes " + logLstart + " - " + (logLstart + logLanz - 1) + "</th></tr>";

	for (var i = 0; i < logRaw.length; i++) {
		var line = logRaw[i];
		var col = "";
		if (line.includes("WARNING")) col = "class='w3-yellow'";
		else if (line.includes("ERROR")) col = "class='w3-red'";
		else if (line.includes("ALARM")) col = "class='w3-purple'";
		var tpos = line.indexOf("(T:");
		if (tpos > 0) {
			var tend = line.indexOf(")", tpos);
			var ltime = parseInt(line.substr(tpos + 3));
			var ldate = new Date(ltime * 1000);
			var tstr = ldate.toLocaleString();

			line = line.substr(0, tpos + 1) + tstr + line.substr(tend);
		}
		hltab += "<tr><td " + col + ">" + line + "</td></tr>";
	}
	hltab += "</table>"; // Close Log
	document.getElementById("showWEAHome").disabled = (logLstart == 0);
	document.getElementById("showWEALeft").disabled = (logLstart == 0);
	document.getElementById("showWEARight").disabled = (logRaw.length != logPageSize);
	document.getElementById("showWEAContent").innerHTML = hltab;
}

function infoGetWEA(lstart, lanz) {
	document.getElementById("showWEAContent").innerHTML = "";
	logLstart = lstart;
	logLanz = lanz;
	logRaw = [];
	user_poll({
		cmd: "getWEA",
		mac: editMAC,
		pos0: lstart,
		anz: lanz
	});
}

function showDeviceWEA(idx) {
	editMAC = deviceWList[idx].mac;
	infoGetWEA(0, logPageSize); // From 0 x Lines
	modal_show("modalShowWEA");
}
//------------- NotesEnd -----------


function clearDeviceData(idx) {
	document.getElementById("edCheckClear").checked = false;
	document.getElementById("edCheckSubmit").disabled = true;
	clearIdx = idx;
	modal_show("modalClearDevice");
}

function clearDeviceDataEnable() {
	document.getElementById("edCheckSubmit").disabled = !document.getElementById("edCheckClear").checked;
}

function clearDeviceSubmit() {
	var clearMAC = deviceWList[clearIdx].mac;

	event.preventDefault();
	modal_close("modalClearDevice");

	console.log("CLEAR " + clearMAC);
	user_poll({
		cmd: "clearDevice",
		mac: clearMAC
	});
}

function infoPosUpdateSelect() { // Update Type of Pos.Selection
	var sel = document.getElementById("infoPosUpdate");
	var nrl = sel.selectedIndex;
	if (nrl == infoObj.posflags) return; // Already set
	//console.log("UpdateSelect:"+nrl);
	user_poll({
		cmd: "setPosUpdate",
		mac: editMAC,
		posflags: nrl
	});
}

function infoEstimatePos() {
	document.getElementById("infoCellular").innerHTML = ""

	if(cellObj == undefined) return
	// If cached Data: use
	if (cellObj.eRad != undefined && cellObj.eLat != undefined && cellObj.eLon != undefined) {
		document.getElementById("infoAccuracy").value = cellObj.eRad;
		document.getElementById("infoLat").value = cellObj.eLat;
		document.getElementById("infoLon").value = cellObj.eLon;
		return;
	}
	document.getElementById("infoAccuracy").value = "";
	document.getElementById("infoLat").value = "";
	document.getElementById("infoLon").value = "";

	//ownAlert("MCC:"+cellObj.mcc+" NET:"+cellObj.net+" LAC:"+cellObj.lac+" CID:"+cellObj.cid);
	user_poll({
		cmd: "getPos",
		mac: editMAC,
		cell: cellObj
	});
}

function edInfoFill() {
	// Make Object from Info
	infoObj = {};
	for (var x = 0; x < editInfo.length; x++) {
		var kv = editInfo[x].split("\t");
		if (kv[1] === undefined) kv[1] = "";
		infoObj[kv[0]] = kv[1];
	}
	//console.log(infoObj);

	var sel = document.getElementById("infoPosUpdate");
	sel.selectedIndex = infoObj.posflags;


	var date0 = new Date(infoObj.date0 * 1000);
	var activeDays = Math.floor((Date.now() - date0) / 86400000);

	var lcon = new Date(infoObj.now * 1000);
	var lage = Math.floor((Date.now() - lcon) / 1000); // Age of last connection

	// Top Info
	var reas;
	switch (infoObj.reason & 15) {
		//case 1: reas="RADIO": break;
		case 2:
			reas = "AUTOMATIC ";
			break;
		case 3:
			reas = "MANUAL ";
			break;
		case 4:
			reas = "START ";
			break;
		default:
			reas = "UNKNOWN(" + infoObj.reason + ") ";
	}
	if (infoObj.reason & 128) reas += "<span class='w3-blue w3-badge'> RESET </span>";
	if (infoObj.reason & 64) reas += "<span class='w3-purple w3-badge jo-blink'> ALARM </span>";
	else if (infoObj.reason & 32) reas += "<span class='w3-purple w3-badge'> (old) ALARM </span>";

	var infoInfo = "<div>" +
		"<div>Last Contact: <b>" + deltaToTimeString(lage) + "</b> ago</div>";
	if (infoObj.expmore > 0) infoInfo += "<div><span class='w3-yellow'><i class='fas fa-exclamation-circle'></i> Last Contact incomplete or pending!&nbsp;</span></div>";
	infoInfo += "<div>Reason: <b>" + reas + "</b></div>";
	infoInfo += "</div>";

	// GeooPos
	var squal

	if (infoObj.signal !== undefined) {
		var cellinfo = infoObj.signal.split(" ");
		cellObj = {};
		for (var i = 0; i < cellinfo.length; i++) {
			var uv = cellinfo[i].split(":");
			cellObj[uv[0]] = uv[1];
		}
		if (cellObj.ta == "255") cellObj.ta = 0; // 255: TA unknown

		if (cellObj.dbm >= -1) {
			squal = "<b><span class='w3-round-large w3-gray'>OK</span></b> (Not measured)"
		} else {
			var qual;
			squal = "<b><span class='w3-round-large w3-"; // Source: Teltonica.lt
			if (cellObj.dbm >= -70) {
				qual = "Top";
				squal += "light-green'>";
			} else if (cellObj.dbm >= -85) {
				qual = "Good";
				squal += "yellow'>";
			} else if (cellObj.dbm >= -100) {
				qual = "Fair";
				squal += "orange'>";
			} else {
				qual = "Poor";
				squal += "red'>";
			} // Poor or none
			squal += cellObj.dbm + " dbm</span></b> (" + qual + ")";
		}
	} else squal = "(Unknown)"

	document.getElementById("infoLastCell").innerHTML = squal;

	// Collapsed Info
	if (infoObj !== undefined) {
		var infoStr = "<div>" +
			"<div>Device Type: <b>" + infoObj.typ + "</b></div>" + // same as HW-Param
			"<div>Active Days: <b>" + activeDays + "</b></div>" +
			"<div>Total Data (up/down in kB): <b>" + (infoObj.total_in / 1024).toFixed(1) + "/" + (infoObj.total_out / 1024).toFixed(1) + "</b></div>";
		if (activeDays > 0) infoStr += "<div>Average Data (up/down in kB/Day): <b>" + (infoObj.total_in / activeDays / 1024).toFixed(1) + "/" + (infoObj.total_out / activeDays / 1024).toFixed(1) + "</b></div>";
		infoStr += "<div>Today (up/down in Bytes): <b>" + infoObj.quota_in + "/" + infoObj.quota_out + "</b></div>";
		infoStr += "<div>Connections (total/OK): <b>" + infoObj.trans + "/" + infoObj.conns + "</b></div>";
		infoStr += "<div>Quota (Days/Lines): <b>" + infoObj.quotad + "/" + infoObj.quotal + "</b>, Push: <b>"
		if(infoObj.quotap !==undefined && infoObj.quotap.length>0) infoStr += "'"+ infoObj.quotap+"'"
		else infoStr += "(NOT SET)"
		infoStr += "</b></div>";

		infoStr += "<div style='font-size: 7px'>&nbsp;</div>";
		var fw_cookieStr;
		if(infoObj.fw_cookie !== undefined){
			var fw_csec = parseInt(infoObj.fw_cookie)
			if (fw_csec < 1526030617 || fw_csec > 0xF0000000) fw_cookieStr = "<span class='w3-yellow'>WARNING: Bootloader Release unknown!</span>";
			else {
				var fwcs = new Date(fw_csec * 1000)
				fw_cookieStr = "ID: " + fw_csec.toString(16).toUpperCase() + " (" + fwcs.toUTCString() + ")"
			}
			infoStr += "<div>Firmware: <b>V" + infoObj.fw_ver / 10 + " " + fw_cookieStr + "</b></div>"; // if undefined: NaN
			infoStr += "<div>Disk (size/available in kB): <b>" + infoObj.dsize + "/" + infoObj.davail + "</b></div>";

			infoStr += "<div style='font-size: 7px'>&nbsp;</div>";
			infoStr += "<div>SIM ICCID: <b>" + infoObj.imsi + "</b></div>";

			infoStr += "<div style='font-size: 7px'>&nbsp;</div>";
		}
		infoStr += "</div>";
		
		document.getElementById("infoInfo").innerHTML = infoInfo;
		document.getElementById("infoDetailsContent").innerHTML = infoStr;

		document.getElementById("infoLogType").selectedIndex = 0;
		logId = -1;
		infoGetLog(0, 0, logPageSize); // 0:Typ, From 0 x Lines
	}

}

function editInfoCallback() { // now all device_info.dat in editInfo
	edInfoFill();
	modal_show("modalEditInfo");
}
var infoDetailsVisible = false;

function expandDetailsInfo() {
	if (infoDetailsVisible) $('#infoDetailsContent').slideUp(500);
	else $('#infoDetailsContent').slideDown(500);
	infoDetailsVisible = !infoDetailsVisible;
}
var infoPositionVisible = false;

function expandPositionInfo() {
	if (infoPositionVisible) $('#infoPositionContent').slideUp(500);
	else $('#infoPositionContent').slideDown(500);
	infoPositionVisible = !infoPositionVisible;
}

var infoLogVisible = false;

function expandLogInfo() {
	if (infoLogVisible) $('#infoLogAllContent').slideUp(500);
	else $('#infoLogAllContent').slideDown(500);
	infoLogVisible = !infoLogVisible;
}

// Get Logfile Type ID, startline (= last) lines 0:Log

function infoGetLog(id, lstart, lanz) {
	document.getElementById("infoLogContent").innerHTML = "";
	logId = id; // Save request Vals
	logLstart = lstart;
	logLanz = lanz;
	logRaw = [];
	//console.log("logId: "+logId);
	user_poll({
		cmd: "getLog",
		mac: editMAC,
		typ: id,
		pos0: lstart,
		anz: lanz
	});
}

function infoShowLogPos(dir) {
	logLstart += (dir * logPageSize);
	if (logLstart < 0) logLstart = 0;
	infoGetLog(logId, logLstart, logPageSize);
}

function editInfFillLog() {
	var hltab = "<table class='w3-table-all'>";
	if (!logId) hltab += "<tr><th>Main Logfile Entries " + logLstart + " - " + (logLstart + logLanz - 1) + "</th></tr>";
	else if (logId == 1) hltab += "<tr><th>Connection Logfile Entries " + logLstart + " - " + (logLstart + logLanz - 1) + "</th></tr>";

	for (var i = 0; i < logRaw.length; i++) {
		hltab += "<tr><td>" + logRaw[i] + "</td></tr>";
	}
	hltab += "</table>"; // Close Log
	document.getElementById("infoHome").disabled = (logLstart == 0);
	document.getElementById("infoLeft").disabled = (logLstart == 0);
	document.getElementById("infoRight").disabled = (logRaw.length != logPageSize);
	document.getElementById("infoLogContent").innerHTML = hltab;
}

function infoLogTypeSelect() {
	var sel = document.getElementById("infoLogType");
	var nrl = sel.selectedIndex;
	if (nrl == logId) return; // Already set
	infoGetLog(nrl, 0, logPageSize); // Else Update
}

function getPosCoords() { // and Check
	var lat = document.getElementById("infoLat").value;
	var lon = document.getElementById("infoLon").value;
	var rad = document.getElementById("infoAccuracy").value;
	if (!rad.length) rad = "0";
	if (lat.length && lon.length && !isNaN(Number(lat)) && !isNaN(Number(lon))) {
		var latf = parseFloat(lat);
		var lonf = parseFloat(lon);
		var radf = parseFloat(rad);
		if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180 && radf >= 0 && radf < 1000000) {
			// Add-Change
			cellObj.lat = latf;
			cellObj.lon = lonf;
			cellObj.rad = radf;
			return true;
		}
	}
	ownAlert("ERROR: ", "Invalid Latitude/Longitude/Accuracy Format");
	return false;
}

function infoShowPos() { // Show Pos in EditFields
	if (getPosCoords() == true) {
		// Open New Window (Google Format)
		window.open(mapUrl + "&q=" + cellObj.lat + "," + cellObj.lon + "&z=12");
	}
}

function infoSavePos() {
	if (getPosCoords() == true) {
		var saveObj = {
			lat: cellObj.lat,
			lon: cellObj.lon,
			rad: cellObj.rad
		}
		var chstr = "MAC:	" + editMAC;
		if (editName.length > 0) chstr += " '" + editName + "'";
		chstr += ": Save Position";
		log_info(chstr, "w3-pale-blue");
		document.getElementById("infoCellular").innerHTML = "Last updated: <b>Now (Saved)</b>";
		user_poll({
			cmd: "savePos",
			mac: editMAC,
			newpos: saveObj
		});
	}
}

function infoClearPos() {
	var chstr = "MAC:	" + editMAC;
	if (editName.length > 0) chstr += " '" + editName + "'";
	chstr += ": Clear Position";
	log_info(chstr, "w3-pale-blue");
	document.getElementById("infoCellular").innerHTML = "Last updated: <b>Now (Cleared)</b>";
	user_poll({
		cmd: "clearPos",
		mac: editMAC
	});
}
//--- Device Infos END ----

// --- Edit Device Parameter Start -------
function editDeviceParameter(idx) { // Show
	editMAC = deviceWList[idx].mac;
	document.getElementById("parMAC").innerText = editMAC;
	editName = deviceWList[idx].name;
	if (editName == null) editName = "";
	editIdx = idx;
	var edname = editName;
	if (!editName.length) edname = "(undefined)";
	document.getElementById("parDeviceName").innerText = edname;
	user_poll({
		cmd: "getParam",
		mac: editMAC
	});
	//console.log("Get Parameter "+editMAC );
}

function edParamChanUp() {
	var ok2save = editParamChannelGet();
	if (ok2save == true) edParamChanUpDownloc(1);
	event.preventDefault();
}

function edParamChanDown() {
	var ok2save = editParamChannelGet();
	if (ok2save == true) edParamChanUpDownloc(-1);
	event.preventDefault();
}
// Callback for Search
function edParamIdxFind(aline) { // Find Index of Current Param
	//console.log("A[" + idx + "]='" + aline + "' ");
	//console.log(typeof aline);
	if (aline !== undefined && aline.charAt(0) == '@' && aline.length > 1) {
		var val = parseInt(aline.substr(1));
		if (val == editAktChan) {
			return true;
		}
	}
}

function edParamChanUpDownloc(dir) {
	var anzChan = parseInt(editDeviceParam[2]);
	if (dir == 1 && editAktChan < anzChan - 1) editAktChan++;
	else if (dir == -1 && editAktChan > 0) editAktChan--;
	document.getElementById("parChannel").innerText = editAktChan;
	var logf = (editAktChan <= 0);
	document.getElementById("parAktDec").disabled = logf;
	logf = (editAktChan >= anzChan - 1);
	document.getElementById("parAktInc").disabled = logf;
	editAktIdx = editDeviceParam.findIndex(edParamIdxFind);
	if (editAktIdx > 0) {
		generate_checks("divActionFlags", cklActionFlags, editDeviceParam[editAktIdx + 1], 0xFF, 0);

		document.getElementById("parKapsList").innerText = editDeviceParam[editAktIdx + 3];
		document.getElementById("parPhysKan").value = editDeviceParam[editAktIdx + 2];
		document.getElementById("parSourceIndex").value = editDeviceParam[editAktIdx + 4];
		document.getElementById("parUnit").value = editDeviceParam[editAktIdx + 5];
		document.getElementById("parMemoryFormat").value = editDeviceParam[editAktIdx + 6];
		document.getElementById("parDBId").value = editDeviceParam[editAktIdx + 7];
		document.getElementById("parOffset").value = editDeviceParam[editAktIdx + 8];
		document.getElementById("parMulti").value = editDeviceParam[editAktIdx + 9];
		document.getElementById("parAlarmHigh").value = editDeviceParam[editAktIdx + 10];
		document.getElementById("parAlarmLow").value = editDeviceParam[editAktIdx + 11];
		document.getElementById("parMessbits").value = editDeviceParam[editAktIdx + 12];
		document.getElementById("parXBytes").value = editDeviceParam[editAktIdx + 13];
	} else {
		generate_checks("divActionFlags", cklActionFlags, 0, 0xFF, 0); // Nothing!
		var oakc, idx1, idx0; // Calcualte Data
		oakc = editAktChan;
		editAktChan = 0;
		idx0 = editDeviceParam.findIndex(edParamIdxFind); // Mit C0: Idx K0
		if (idx0 < 19) { // V1.0
			ownAlert("FATAL ERROR:", "Parameter File invalid (F)");
		}
		editAktChan = 1;
		idx1 = editDeviceParam.findIndex(edParamIdxFind); // Mit C1: Idx k1
		if (idx1 < 0) { // 1 Channel: Not Found: Calculate
			idx1 = editDeviceParam.length;
		}
		editAktChan = oakc;
		editAktIdx = oakc * (idx1 - idx0) + idx0; // Calculate Index
		ownAlert("Info:", "Adding new Channel #" + editAktChan);
	}
	showSecondary(0);
}

function removePendingParam() {
	modal_close("modalEditParameter");

	var chstr = "MAC:	" + editMAC;
	if (editName.length > 0) chstr += " '" + editName + "'";
	chstr += ": Remove waiting Hardware-Parameters";
	log_info(chstr, "w3-pale-blue");
	user_poll({
		cmd: "removePending",
		mac: editMAC
	});
	document.getElementById("modalSpinner").style.display = "block";
	spinnerVisible = true;
}

function edParamFormFill() { // Fill Parameters with act. chan
	//console.log(editDeviceParam);
	var info = "<div>" +
		"<div>Device Type: <b>" + editDeviceParam[1] + "</b></div>" +
		"<div>No. of Channels: <b>" + editDeviceParam[2] + "</b></div>" +
		"<div>Parameter on Device ('Cookie'): <b>" + editSCookie.replace(" ", "&nbsp;") + "</b></div>" +
		"</div>";

	if (editParPending) {
		info += "<div><span class='w3-yellow'><i class='fas fa-exclamation-circle'></i> Parameters still waiting for Transfer!&nbsp;</span><br>" +
			"<button class='w3-button w3-green' onclick='removePendingParam()'> Remove waiting Parameters</button></div>";
	}

	document.getElementById("parInfo").innerHTML = info;

	// Inputs
	document.getElementById("parName").value = editDeviceParam[5];
	document.getElementById("parPeriodMeasure").value = editDeviceParam[6];
	document.getElementById("parPeriodOffset").value = editDeviceParam[7];
	document.getElementById("parPeriodAlarm").value = editDeviceParam[8];
	document.getElementById("parUTCOffset").value = editDeviceParam[11];
	document.getElementById("parPeriodInternet").value = editDeviceParam[9];
	document.getElementById("parPeriodInternetAlarm").value = editDeviceParam[10];

	generate_checks("divRecFlags", cklRecFlags, editDeviceParam[12], 0xFF, 0); // 3 is Mask
	generate_checks("divHKFlags", cklHKFlags, editDeviceParam[13], editDeviceParam[3], 0); // 3 is Mask
	document.getElementById("parHKCounter").value = editDeviceParam[14];
	generate_drops("selNetMode", optNetModes, editDeviceParam[15]);

	generate_drops("selErrorPolicy", optErrorPolicy, editDeviceParam[16]);

	document.getElementById("parMinTemp").value = editDeviceParam[17];
	document.getElementById("parConfig0").value = editDeviceParam[18];

	// Zusatzparameter 
	document.getElementById("parCmdConfig").value = (editIndexChan0>19)?editDeviceParam[19]:''
	
	edParamChanUpDownloc(0);
}
// Get Parameters from MAIN-Part
function editParamMainGet() {
	var getv;
	getv = $("#parName").val()
	editDeviceParam[5] = getv.replace("@", "?").replace("#", "?")

	getv = $("#parPeriodMeasure").val();
	if (getv < 60) {
		ownAlert("ERROR:", "Measure Period >= 60 s");
		return false;
	}
	editDeviceParam[6] = getv; // Period

	getv = $("#parPeriodOffset").val();
	if (getv >= parseInt(editDeviceParam[6])) {
		ownAlert("ERROR:", "Period Offset >= Period");
		return false;
	}
	editDeviceParam[7] = getv; // PeriodOffset

	getv = $("#parPeriodAlarm").val();
	if (getv != 0 && getv > parseInt(editDeviceParam[6])) {
		ownAlert("ERROR:", "Alarm Period > Period");
		return false;
	}
	editDeviceParam[8] = getv; // AlarmPeriod

	getv = $("#parUTCOffset").val();
	editDeviceParam[11] = getv; // UTC Offset

	getv = $("#parPeriodInternet").val();
	if (getv != 0 && getv < parseInt(editDeviceParam[6])) {
		ownAlert("ERROR:", "Internet Period < Period");
		return false;
	} else if (getv != 0 && getv < 3600) {
		ownAlert("Info:", "Fast Internet Period (" + getv + "s) OK? See Manual for Battery Live.");
	}
	editDeviceParam[9] = getv;

	getv = $("#parPeriodInternetAlarm").val();
	if (getv != 0 && getv < parseInt(editDeviceParam[8])) {
		ownAlert("ERROR:", "Internet Alarm Period < Alarm Period");
		return false;
	} else if (getv != 0 && getv > parseInt(editDeviceParam[9])) {
		ownAlert("ERROR:", "Internet Alarm Period > Internet Period");
		return false;
	} else if (getv > 0 && getv < 1800) {
		ownAlert("Info:", "Fast Internet Alarm Period (" + getv + "s) OK? See Manual for Battery Live.");
	}
	editDeviceParam[10] = getv;

	getv = eval_checks("divRecFlags");
	if (!getv) {
		ownAlert("WARNING:", "Record OFF");
	}
	editDeviceParam[12] = getv.toString();

	editDeviceParam[13] = eval_checks("divHKFlags").toString();

	getv = $("#parHKCounter").val();
	editDeviceParam[14] = getv; // HK-Reoad Counter

	getv = eval_drops("selNetMode");
	if (!getv) {
		ownAlert("STRONG WARNING:", "Internet Transmission set to OFF");
	} else if (getv == 3) {
		ownAlert("Info:", "Mode 'Stay ONLINE' OK? See Manual for Battery Live.");
	}

	editDeviceParam[15] = getv.toString();

	editDeviceParam[16] = eval_drops("selErrorPolicy").toString(); // Error Policy

	getv = $("#parMinTemp").val();
	editDeviceParam[17] = getv; // MinTemp

	getv = $("#parConfig0").val();
	editDeviceParam[18] = getv;

	if(editIndexChan0>19){	// Zusatzparameter NUR bei Bedarf uebernehmen
		getv = $("#parCmdConfig").val();
		editDeviceParam[19] = getv.replace("@", "?").replace("#", "?")
	}

	return true;

}
// Get Parameters from Channel
function editParamChannelGet() {

	var idx = editAktIdx;
	editDeviceParam[idx] = "@" + editAktChan; // Name fix
	var getv;

	editDeviceParam[idx + 1] = eval_checks("divActionFlags").toString(); // ActionFlags

	var kaps = document.getElementById("parKapsList").innerText.replace("@", "?").replace("#", "?");
	editDeviceParam[idx + 3] = kaps; // Physkan List (Kaps)

	getv = $("#parPhysKan").val();
	editDeviceParam[idx + 2] = getv; // Physkan

	getv = $("#parSourceIndex").val();
	editDeviceParam[idx + 4] = getv; // SrcIndex(if Cache)

	getv = $("#parUnit").val();
	editDeviceParam[idx + 5] = getv.replace("@", "?").replace("#", "?"); // Units

	getv = $("#parMemoryFormat").val();
	editDeviceParam[idx + 6] = getv; // MemoryFormat

	getv = $("#parDBId").val();
	editDeviceParam[idx + 7] = getv; // DB Index

	getv = $("#parOffset").val();
	if (isNaN(Number(getv))) {
		ownAlert("ERROR:", "'Offset': must be FLOAT");
		return false;
	}
	editDeviceParam[idx + 8] = getv; // Offset

	getv = $("#parMulti").val();
	if (isNaN(Number(getv))) {
		ownAlert("ERROR:", "'Multi': must be FLOAT");
		return false;
	}
	editDeviceParam[idx + 9] = getv; // Multi

	getv = $("#parAlarmHigh").val();
	if (isNaN(Number(getv))) {
		ownAlert("ERROR:", "'Alarm High': must be FLOAT");
		return false;
	}
	editDeviceParam[idx + 10] = getv; // Alarm Hi

	getv = $("#parAlarmLow").val();
	if (isNaN(Number(getv))) {
		ownAlert("ERROR:", "'Alarm Low': must be FLOAT");
		return false;
	}
	editDeviceParam[idx + 11] = getv; // Alarm Lo

	getv = $("#parMessbits").val();
	editDeviceParam[idx + 12] = getv; // Messbits

	getv = $("#parXBytes").val();
	editDeviceParam[idx + 13] = getv.replace("@", "?").replace("#", "?"); // Name
	return true;
}
// Reduce edited Array to the Minimum. Tricky: Remove unused entries from end
function editParamClip2Used() {
	var oakc = editAktChan;
	var maxchan = editDeviceParam[2]; // No of channels of Device
	for (var i = maxchan - 1; i > 0; i--) {
		editAktChan = i;
		var x0 = editDeviceParam.findIndex(edParamIdxFind);
		//console.log("Check #"+i);
		if (x0 < 0) continue; // Channel not present
		if (editDeviceParam[x0 + 1] & 1) break; // Action-Bit set! Channel in use! End
		// else: Remove Rest of Array:	
		//console.log("Remove #"+i);
		var anzrem = editDeviceParam.length - x0;
		editDeviceParam.splice(x0, anzrem);
	}
	editAktChan = oakc;
}

function editParamCheck2Org() { // Check against Original - return No of changes
	var chgcnt = 0;
	var elen = editDeviceParam.length;
	for (var i = 0; i < elen; i++) {
		if (editDeviceParam[i] == editOrgDeviceParam[i]) continue;
		chgcnt++;
	}
	if (elen < editOrgDeviceParam.length) chgcnt++; // New or less channels also counts
	return chgcnt; // No. of Changes
}

function editParameterSubmit() {
	var ok2save = editParamMainGet(); // First Get MAIN Parameter
	var changes;
	event.preventDefault();
	if (ok2save == true) {
		ok2save = editParamChannelGet(); // Get open Chan Data to
		if (ok2save == true) {
			editParamClip2Used(); // Clip edited Parameter to minimum
			changes = editParamCheck2Org(); // Check if different from Original
		}
	}
	if (ok2save) { // Send what is changed
		// Check for changes
		modal_close("modalEditParameter");

		if (changes) {
			// New Cookie
			editDeviceParam[4] = Math.floor(Date.now() / 1000);
			//console.log("---- Parameter Modified Len:"+editDeviceParam.length+" Changes:"+ changes+" -------");
			//console.log(editDeviceParam);

			var chstr = "MAC:	" + editMAC;
			if (editName.length > 0) chstr += " '" + editName + "'";
			chstr += ": Parameter Changes (" + changes + ") saved";
			log_info(chstr, "w3-pale-blue");
			user_poll({
				cmd: "saveParam",
				mac: editMAC,
				npar: editDeviceParam
			});
			document.getElementById("modalSpinner").style.display = "block";
			spinnerVisible = true;

		}
	}
}

function editParamCallback() {
	editOrgDeviceParam = [];
	for (var i = 0; i < editDeviceParam.length; i++) { // Deep Copy
		editOrgDeviceParam[i] = editDeviceParam[i]; // Keep Original
	}
	editAktChan = 0;
	editIndexChan0 = editDeviceParam.findIndex(edParamIdxFind); // Mit C0: Idx K0
	edParamFormFill();
	modal_show("modalEditParameter");
}
var parMainVisible = false;

function expandMainParameter() {
	if (parMainVisible) $('#parMainDetails').slideUp(500);
	else $('#parMainDetails').slideDown(500);
	parMainVisible = !parMainVisible;
}
var parChanDetVisible = false;

function expandChanParameter() {
	if (parChanDetVisible) $('#parChanDetails').slideUp(500);
	else $('#parChanDetails').slideDown(500);
	parChanDetVisible = !parChanDetVisible;
}

// --- Edit Device Parameter End -------


// --- Edit Device Details -------
function editDeviceDetails(idx) { // Show
	user_poll({
		cmd: "getDevice",
		mac: deviceWList[idx].mac
	});
	//console.log("Get Details "+deviceWList[idx].mac );
}

// --- Helper: Fill out current form after new editDeviceData -----
function edDeviceFormFill() { // And initialise NULL elements
	var isguest = (editDeviceData.owner_id != userID);
	if (userRole & 65536) isguest = false; // ADMIN!

	//console.log(editDeviceData);
	document.getElementById("edMAC").innerText = editDeviceData.mac;


	if (editDeviceData.last_seen == null) editDeviceData.last_seen = "(Never)";
	if (editDeviceData.last_change == null) editDeviceData.last_change = "(Never)";

	var devinf = "<div>" +
		"<div>Total Transfers: <b>" + editDeviceData.transfer_cnt + "</b></div>" +
		"<div>Total Lines Data: <b>" + editDeviceData.lines_cnt + "</b></div>" +
		"<div>Lines Data (in Database): <b>" + editDeviceData.available_cnt + "</b></div>" +
		"<div>Last seen: <b>" + editDeviceData.last_seen.replace(" ", "&nbsp;") + "</b></div>" +
		"<div>Last Change: <b>" + editDeviceData.last_change.replace(" ", "&nbsp;") + "</b></div>" +
		"<div>First seen: <b>" + editDeviceData.first_seen.replace(" ", "&nbsp;") + "</b></div>" +
		"<div>Parameter on Server ('Cookie'): <b>" + editDeviceData.sCookie.replace(" ", "&nbsp;") + "</b></div>" +
		"</div>";

	if (isguest) {
		devinf = "<div class='w3-text-orange'>(Guest Device)</div>" + devinf;
	}
	document.getElementById("edInfo").innerHTML = devinf;

	document.getElementById("edGenToken0").disabled = isguest;

	if (editDeviceData.name == null) editDeviceData.name = "";
	document.getElementById("edDeviceName").innerText = editDeviceData.name;

	document.getElementById("edUTCOffset").value = editDeviceData.utc_offset;

	document.getElementById("edTimeoutWarn").value = editDeviceData.timeout_warn;
	document.getElementById("edTimeoutErr").value = editDeviceData.timeout_alarm;
	var batf = (editDeviceData.flags) & 7;
	generate_drops("selBatt", optEdBatt, batf);
	document.getElementById("edCheckHum").checked = (editDeviceData.flags) & 8;

	generate_checks("divRole0", cklUserRoles, editDeviceData.role0, cklMaskRoles, isguest ? 0xFFFF : 0);
	if (editDeviceData.token0 == null) editDeviceData.token0 = "";
	var dtok = document.getElementById("edToken0");
	dtok.style.color = 'gray';
	dtok.value = editDeviceData.token0;

	if (editDeviceData.email0 == null) editDeviceData.email0 = "";
	document.getElementById("edDeviceMail0").value = editDeviceData.email0;
	if (editDeviceData.cond0 == null) editDeviceData.cond0 = "";
	document.getElementById("edMailCond0").value = editDeviceData.cond0;
	document.getElementById("edBadge0").innerText = editDeviceData.em_cnt0;

	var lastcont = "(Never)";
	if (editDeviceData.em_date0 != null) lastcont = editDeviceData.em_date0;
	document.getElementById("edLastSent0").innerText = lastcont;
}

function editDeviceCallback() {
	edDeviceFormFill();
	modal_show("modalEditDevice");
}

function editDeviceSubmit() {
	var ok2save = true; // Add. verification
	var changecnt = 0;
	var newutc = parseInt($("#edUTCOffset").val());
	if (newutc != editDeviceData.utc_offset) changecnt++;
	var newtow = parseInt($("#edTimeoutWarn").val());
	if (newtow != editDeviceData.timeout_warn) changecnt++;
	var newtoa = parseInt($("#edTimeoutErr").val());
	if (newtoa != editDeviceData.timeout_alarm) changecnt++;
	var newflags = eval_drops("selBatt"); // 3 Bits
	if (document.getElementById("edCheckHum").checked) newflags |= 8;
	// other
	if (newflags != editDeviceData.flags) changecnt++;
	var newrole0 = eval_checks("divRole0").toString();
	if (newrole0 != editDeviceData.role0) changecnt++;
	var newtoken0 = $("#edToken0").val();
	if (newtoken0.length != 16) newtoken0 = "";
	if (newtoken0 != editDeviceData.token0) changecnt++;
	var newcontact0 = $("#edDeviceMail0").val();
	if (newcontact0 != editDeviceData.email0) changecnt++;
	var newcond0 = $("#edMailCond0").val();
	if (newcond0 != editDeviceData.cond0) changecnt++;
	event.preventDefault();
	modal_close("modalEditDevice");

	if (ok2save && changecnt > 0) { // Send what is changed
		lastSeenTimestamp = 0; // Get ALL
		userAnzDevices = 0; // Neu zeichnen!
		var chstr = "MAC:	" + editDeviceData.mac;
		chstr += ": Server-Setup Changes (" + changecnt + ") saved";
		log_info(chstr, "w3-pale-green");
		user_poll({
			cmd: "changeDevice",
			mac: editDeviceData.mac,
			new_utcoffset: newutc,
			new_towarn: newtow,
			new_toalarm: newtoa,
			new_flags: newflags,
			new_role0: newrole0,
			new_token0: newtoken0,
			new_email0: newcontact0,
			new_cond0: newcond0,
		});
		document.getElementById("modalSpinner").style.display = "block";
		spinnerVisible = true;
	} // Name unchanged;
}

// Sub-Functions foe Edit Device
function edGenerateNewToken(idx) {
	var newtok = "",
		dtok;
	dtok = document.getElementById("edToken0");
	for (var i = 0; i < 16; i++) newtok += Math.floor(Math.random() * 16).toString(16);
	dtok.value = newtok.toUpperCase();
	dtok.style.color = 'black';
}

function edCopyTokenToClipboard(idx) {
	var dtok = document.getElementById("edToken" + idx);
	dtok.disabled = false;
	dtok.select();
	dtok.setSelectionRange(0, 99999); // For mobile devices
	var clb = dtok.value;
	var cpytxt = `MAC:${editDeviceData.mac}\nTOK${idx}:${clb}`
	dtok.disabled = true;
	navigator.clipboard.writeText(cpytxt)
	if (clb.length != 16) ownAlert("ERROR:", "Token #" + idx + ": (None)");
	else ownAlert("Copied to Clipboard", "<b>MAC:" + editDeviceData.mac + " <br>TOK" + idx + ":" + clb + "</b><br><br>(Don't forget to 'SAVE CHANGES')");
}

function checkSaved() { // true if all changed
	if (parseInt($("#edUTCOffset").val()) != editDeviceData.utc_offset) return false;
	if (parseInt($("#edTimeoutWarn").val()) != editDeviceData.timeout_warn) return false;
	if (parseInt($("#edTimeoutErr").val()) != editDeviceData.timeout_alarm) return false;
	var newflags = eval_drops("selBatt"); // 3 Bits
	if (document.getElementById("edCheckHum").checked) newflags |= 8;
	// other
	if (newflags != editDeviceData.flags) return false;
	if (eval_checks("divRole0").toString() != editDeviceData.role0) return false;
	if ($("#edToken0").val() != editDeviceData.token0) return false;
	if ($("#edDeviceMail0").val() != editDeviceData.email0) return false;
	if ($("#edMailCond0").val() != editDeviceData.cond0) return false;
	return true;
}

function edMailTest0() {
	var newcontact0 = $("#edDeviceMail0").val();
	if (checkSaved() != true || newcontact0.length < 5) { // Might later be something else than Mail
		ownAlert("ERROR:", "Contact invalid or Changes not saved");
	} else {
		ownAlert("Test Contact", "Test Message sent to: '" + editDeviceData.email0 + "'");
		var mcont = "Test 'Contact #0' from User '" + userName + "'";
		/*
		var mcont="Test 'Contact #0' for MAC:"+editDeviceData.mac;
		if(typeof editDeviceData.name == 'string' && editDeviceData.name.length>0){
			mcont+=" '"+editDeviceData.name+"'";
		}
		*/
		user_poll({
			cmd: "testContact",
			contNo: 0,
			mac: editDeviceData.mac,
			xcont: mcont /*, mail:editDeviceData.email0*/
		});
	}
	event.preventDefault();
}

function edResetCond0() {
	event.preventDefault();

	if (checkSaved() != true) { // Might later be something else than Mail
		ownAlert("ERROR:", "Changes not saved");
	} else {
		user_poll({
			cmd: "cntReset",
			contNo: 0,
			mac: editDeviceData.mac
		});
	}
}

// --- Edit Device Details End -----

// ---- Edit User (only possible: Change user Name) -------
function editUserSubmit() {
	var newname = $("#aUserName").val();
	event.preventDefault();
	modal_close("modalEditUser");
	if (newname != userData.name) { // Send what is changed
		user_poll({
			cmd: "changeUser",
			new_name: newname
		});
		document.getElementById("modalSpinner").style.display = "block";
		spinnerVisible = true;
	} // Name unchanged;
}

function editUserCallback() {
	//console.log(userData);
	document.getElementById("aUserID").innerText = userData.id;
	var ud = new Date(userData.created_at);
	document.getElementById("aUserCreated").innerText = ud.toLocaleDateString();
	document.getElementById("aUserName").value = userData.name;
	document.getElementById("aUserEmail").value = userData.email;
	modal_show("modalEditUser");
}

function editUserShow() { // Aquire User-Data
	user_poll({
		cmd: "getUser"
	});
}
// ---- Edit User End---

function legacyMainShow() { // SpringInLLegacy
	//alert("Hallo Admin")
	window.open('../legacy/index.php', '_blank');
}


//------ Test ------
function test() {
	// Show Devicelist
	console.log("Devicelist, len:" + deviceWList.length);
	for (var i = 0; i < deviceWList.length; i++) console.log(deviceWList[i]);
}

/* function do_msb_scroll(){
	// Platz fuer Scrollspy
} */

function initScripts() {
	document.getElementById("versInfo").innerText = prgVersion;

	if (location.protocol != "https:" && location.hostname != "localhost") {
		window.location.assign("login.php");
		return;
	}

	$.ajaxSetup({
		type: 'POST',
		timeout: 15000, // 15 secs Time
		error: function (xhr, status, error) { // error string already in xhr
			document.getElementById("modalSpinner").style.display = "none";
			spinnerVisible = false;
			var errorMessage = xhr.status + ': ' + xhr.statusText + " - AJAX:'" + error + "'";
			if (xhr.responseText !== undefined) errorMessage += " <br>" + xhr.responseText;
			ownAlert("ERROR:", errorMessage);
		}
	});
	document.title = prgName;

	$("#joSky").fadeOut(400);
	//document.addEventListener('scroll', do_msb_scroll );
	$("#addMacForm").submit(addSubmit); // Same as addEventListener
	$("#removeMacForm").submit(removeSubmit);
	$("#editUserForm").submit(editUserSubmit);
	$("#editDeviceForm").submit(editDeviceSubmit);
	$("#editParameterForm").submit(editParameterSubmit);
	$("#clearDeviceForm").submit(clearDeviceSubmit);

	document.getElementById("showSecPar").checked = false;

	user_poll();
	setInterval(secTickTimer, 1000);

}

//---------- M A I N -----------
window.addEventListener('load', initScripts);

//End