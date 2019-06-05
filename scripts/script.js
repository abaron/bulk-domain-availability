window.domains       = [];
window.domainsError  = [];
window.domainsLength = 0;
window.isPaused      = false;

$('form#availability-form').submit(function(e) {
    e.preventDefault();

    domains = [];
    domainsLength = 0;
    isPaused   = false;
    $('#progress, #domains-checked').html(0);
    $('#available-counter span, #unavailable-counter span, #error-counter span').html(0);
    $('.retry-error').addClass('hidden');

    var tmpDomains = $('textarea[name="domains"]').val().replace(/\n/g, " ").split(" ");
    var tlds = $('select[name="tlds"]').val();

    // trim all values
    $.map(tmpDomains, $.trim);

    // remove empty values
    var tmpDomains = tmpDomains.filter(function (el) {
        return el != '';
    });

    // generate domains with tld
    $.each(tmpDomains, function(kdomain, domain) {

        $.each(tlds, function(ktld, tld) {
            if (isValidDomain(domain)) {
                window.domains.push(domain);

                return true;
            }

            domain = addPrefixSuffix(domain);

            if (isValidDomain(domain + '.' + tld) && isValidLength(domain)) {
                window.domains.push(domain + '.' + tld);
            }
        });
    });

    // combine without separator
    if ($('input[name=combine][value=1]:checked').length) {
        $.each(tmpDomains, function(kdomain, domain) {
            $.each(tmpDomains, function(kdomain2, domain2) {
                if (!isValidDomain(domain) && !isValidDomain(domain2)) {
                    $.each(tlds, function(ktld, tld) {
                        var domainCombined = addPrefixSuffix(domain + domain2);

                        if (isValidDomain(domainCombined + '.' + tld) && isValidLength(domainCombined)) {
                            window.domains.push(domainCombined + '.' + tld);
                        }
                    });
                }
            });
        });
    }

    // combine with "-" as separator
    if ($('input[name=combine][value=2]:checked').length) {
        $.each(tmpDomains, function(kdomain, domain) {
            $.each(tmpDomains, function(kdomain2, domain2) {
                if (!isValidDomain(domain) && !isValidDomain(domain2)) {
                    $.each(tlds, function(ktld, tld) {
                        var domainCombined = addPrefixSuffix(domain + '-' + domain2);

                        if (isValidDomain(domainCombined + '.' + tld) && isValidLength(domainCombined)) {
                            window.domains.push(domainCombined + '.' + tld);
                        }
                    });
                }
            });
        });
    }

    // to unique
    domains = Array.from(new Set(domains));

    // remove empty value (reorder)
    domains.filter(n => n);

    domains.reverse();

    domainsLength = domains.length;

    $('#domains-length').html(domainsLength);

    $('.result').removeClass('hidden');

    if (domains.length) {
        $('#pause, #stop').removeClass('hidden');
        $('#search, #resume').addClass('hidden');

        requestAvailability();
    }
});

$(document).on('click', '.close-whois', function(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
});

$(document).on('click', '[data-whois]', function(e) {
    e.preventDefault();
    var whoisElem = $(this);
    var close = '<a href="javascript:void(0);" class="close-whois">Close</a><br />';

    if (whoisElem.closest('tr').next().attr('class') == 'result-whois') {
        whoisElem.closest('tr').next().remove();
        return true;
    }

    $.ajax({
        url: "",
        type: "post",
        data: "domain=" + $(this).data('whois') + '&whois=1',
        success: function(res) {
            $('<tr class="result-whois"><td colspan="9">' + close + res.data.whois_data.replace(new RegExp('\r?\n','g'), '<br />') + '</td></tr>').insertAfter(whoisElem.closest('tr'));
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('<tr class="result-whois"><td colspan="9">' + close + 'Failure get whois data</td></tr>').insertAfter(whoisElem.closest('tr'));
        }
    });
});

function requestAvailability() {
    if (!domains.length) {
        stop();

        return true;
    } if (isPaused) {
        pause();

        return true;
    } else if (typeof(Storage) !== "undefined" && localStorage.getItem(domains[domains.length-1])) {
        $('#checking span').html(domains[domains.length-1]);

        var start = new Date().getTime();

        var response = JSON.parse(localStorage.getItem(domains[domains.length-1]));

        var end = new Date().getTime();
        response.data.execute_time = end - start;
        response.data.execute_time_unit = 'ms';

        writeTable(response, 'cache');

        requestDone();

        return true;
    }

    $('#checking span').html(domains[domains.length-1]);

    $.ajax({
        url: "",
        type: "post",
        data: "domain=" + domains[domains.length-1],
        timeout: 7000,
        success: function(res) {
            writeTable(res, window.location.hostname);

            if ($('input[name=cache][value=1]:checked').length && typeof(Storage) !== "undefined") {
                delete res.data.execute_time;
                delete res.data.execute_time_unit;

                try {
                    window.localStorage.setItem(res.data.domain, JSON.stringify(res));
                }
                catch (e) {
                    console.log("Local Storage is full");
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // console.log(textStatus, errorThrown);

            var res = {};
            res.data = {};
            res.code = 500;
            res.status = 'Error';
            res.message = 'Server time out';
            res.data.is_available = false;
            res.data.domain = domains[domains.length-1];
            res.data.whois_server = '-';
            res.data.execute_time = '-';
            res.data.execute_time_unit = '';
            window.domainsError.push(domains[domains.length-1]);

            // add space for error flag
            writeTable(res, window.location.hostname + ' ');
            requestDone();
            $('.retry-error').removeClass('hidden');
        }
    }).done(function(data) {
        requestDone();
    });
}

function requestDone() {
    domains = domains.filter(function(value, index, arr){
        return value != domains[domains.length-1];
    });

    $('#domains-checked').html(parseInt($('#domains-checked').html()) + 1);
    $('#progress').html(((parseInt($('#domains-checked').html())) * 100 / domainsLength).toFixed(2));

    if (domains.length) {
        requestAvailability();
    } else {
        stop();
    }
}

function writeTable(res, resource) {
    if (res.data.is_available) {
        $('#available-counter span').html(
            (parseInt($('#available-counter span').html()) + 1)
        );
    } else if (resource == window.location.hostname + ' ') {
        $('#error-counter span').html(
            (parseInt($('#error-counter span').html()) + 1)
        );
    } else {
        $('#unavailable-counter span').html(
            (parseInt($('#unavailable-counter span').html()) + 1)
        );
    }

    // fix numbering start from 1 if last tr is whois row
    if ($('#result-table tbody tr:last').attr('class') == 'result-whois') {
        $('#result-table tbody tr:last').remove();
    }

    $('#result-table').append(
        '<tr class="' + (res.data.is_available ? '' : 'error row-unavailable') + '">\
            <td>' + ($('#result-table tbody tr:last:not(.result-whois) td:first').length ? (parseInt($('#result-table tbody tr:last td:first').html()) + 1) : '1') + '</td>\
            <td>' + res.data.domain + '</td>\
            <td>' + (res.data.is_available ? 'Available' : 'Unavailable') + '</td>\
            <td>' + (res.data.domain.match(/([^.]+)\.\w{2,12}(?:\.\w{2})?$/) || [[],[]])[1].length + '</td>\
            <td>' + (res.message == '' ? '-' : res.message) + '</td>\
            <td class="clm-whois">' + (res.data.whois_server) + '</td>\
            <td class="clm-resource">' + resource + '</td>\
            <td class="clm-exec-time">' + res.data.execute_time + res.data.execute_time_unit + '</td>\
            <td>' + (res.data.is_available ? '<a href="https://www.godaddy.com/domainsearch/find?domainToCheck=' + res.data.domain + '" target="_blank">Buy Now</a>' : '<a href="http://' + res.data.domain + '" target="_blank">www</a> | <a href="javascript:void(0);" data-whois="' + res.data.domain + '">whois</a>') + '</td>\
        </tr>'
    );
}

$('#availability-form [type=reset]').click(function(e) {
    e.preventDefault();

    var result = confirm("Are you sure to reset form and delete all result?");
    if (result) {
        $('#result-table tbody').html('');
        $(this).closest('form')[0].reset();
        $('#available-counter span').html(0);
        $('#unavailable-counter span').html(0);
        $('#error-counter span').html(0);
        $('#error-counter label').addClass('hidden');
    }
});

$('#clear-cache').click(function(e) {
    e.preventDefault();

    var result = confirm("Are you sure to clear all cache?");
    if (result) {
        localStorage.clear();
    }
});

$("#hide-whois").click(function(e, parameters) {
    var nonUI = false;
    try {
        nonUI = parameters.nonUI;
    } catch (e) {}
    var checked = nonUI ? !this.checked : this.checked;

    if (checked) {
        $('head')
            .append('<style type="text/css" media="screen" id="css-clm-whois">.clm-whois{display: none;}</style>');
    } else {
        $('#css-clm-whois').remove();
    }
});

$("#hide-exec-time").click(function(e, parameters) {
    var nonUI = false;
    try {
        nonUI = parameters.nonUI;
    } catch (e) {}
    var checked = nonUI ? !this.checked : this.checked;

    if (checked) {
        $('head')
            .append('<style type="text/css" media="screen" id="css-clm-exec-time">.clm-exec-time{display: none;}</style>');
    } else {
        $('#css-clm-exec-time').remove();
    }
});

$("#hide-resource").click(function(e, parameters) {
    var nonUI = false;
    try {
        nonUI = parameters.nonUI;
    } catch (e) {}
    var checked = nonUI ? !this.checked : this.checked;

    if (checked) {
        $('head')
            .append('<style type="text/css" media="screen" id="css-clm-resource">.clm-resource{display: none;}</style>');
    } else {
        $('#css-clm-resource').remove();
    }
});

$("#hide-unavailable").click(function(e, parameters) {
    var nonUI = false;
    try {
        nonUI = parameters.nonUI;
    } catch (e) {}
    var checked = nonUI ? !this.checked : this.checked;

    if (checked) {
        $('head')
            .append('<style type="text/css" media="screen" id="css-row-unavailable">.row-unavailable{display: none;}</style>');
    } else {
        $('#css-row-unavailable').remove();
    }
});

$('#pause').click(function(e) {
    e.preventDefault();
    pause();
});

$('#resume').click(function(e) {
    e.preventDefault();
    resume();
});

$('#stop').click(function(e) {
    e.preventDefault();

    var result = confirm("Are you sure to stop?");
    if (result) {
        stop();
    }
});

$('#retry-error').click(function(e) {
    e.preventDefault();

    $('#domains-checked').html(parseInt($('#domains-checked').html()) - domainsError.length);
    $('#progress').html(((parseInt($('#domains-checked').html())) * 100 / domainsLength).toFixed(2));
    $('.retry-error').addClass('hidden');
    $('#error-counter span').html(0);


    domains = [...domainsError, ...domains];
    domainsError = [];

    // if finished or stopped
    if (parseInt($('#progress').html()) <= 0) {
        domainsLength = domains.length;

        $('#domains-checked').html(0);
        $('#domains-length').html(domains.length);

        $('#pause, #stop').removeClass('hidden');
        $('#search, #resume').addClass('hidden');

        requestAvailability();
    }
});

function pause() {
    isPaused = true;

    $('#pause').addClass('hidden');
    $('#resume').removeClass('hidden');

    $('#checking span').html('paused');
}

function resume() {
    isPaused = false;

    $('#pause').removeClass('hidden');
    $('#resume').addClass('hidden');

    requestAvailability();
}

function stop() {
    isPaused = false;
    domains  = [];

    $('#pause, #resume, #stop').addClass('hidden');
    $('#search').removeClass('hidden');

    $('#domains-checked, #domains-length, #progress').html(0);

    $('#checking span').html('-');
}

$('table').each(function () {
    var $table = $(this);

    var $button = $("<button download='bulk-domain-availability.csv' type='button' style='margin-bottom:10px'>");
    $button.text("Export to CSV");
    $button.insertBefore($table);


    $button.click(function () {
        var generateFilename = "bulk-domain-availability999.csv";

        if ($("#result-table tbody tr:visible:first td:eq(1)").text() != "") {
            generateFilename = $("#result-table tbody tr:visible:first td:eq(1)").text() + '-' +
                $("#result-table tbody tr:visible:last td:eq(1)").text() + '-' +
                $("#result-table tbody tr:visible").length + '.csv';
        }

        var csv = $table.table2csv({
            filename: generateFilename,
            excludeColumns: 'td:last-child, th:last-child'
        });
    });
});

function addPrefixSuffix(domain) {
    if ($('input[name="prefix"]').val() != "") {
        domain = $('input[name="prefix"]').val() + domain;
    }

    if ($('input[name="suffix"]').val() != "") {
        return domain + $('input[name="suffix"]').val();
    }

    return domain;
}

function isValidLength(domain) {
    if ($('input[name="max-length"]').val() != "") {
        return domain.length <= parseInt($('input[name="max-length"]').val());
    }

    return true;
}

(function(root) {

    function isValidDomain(v, opts) {
        if (typeof v !== 'string') return false
        if (!(opts instanceof Object)) opts = {}

        var parts = v.split('.')
        if (parts.length <= 1) return false

        var tld = parts.pop()
        var tldRegex = /^(?:xn--)?[a-zA-Z0-9]+$/gi

        if (!tldRegex.test(tld)) return false
        if (opts.subdomain == false && parts.length > 1) return false

        var isValid = parts.every(function(host, index) {
            if (opts.wildcard && index === 0 && host === '*' && parts.length > 1) return true

            var hostRegex = /^(?!:\/\/)([a-zA-Z0-9]+|[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9])$/gi;

            return hostRegex.test(host)
        })

        return isValid
    }

    if (typeof exports !== 'undefined') {
        if (typeof module !== 'undefined' && module.exports) {
            exports = module.exports = isValidDomain;
        }
        exports.isValidDomain = isValidDomain;
    } else if (typeof define === 'function' && define.amd) {
        define([], function() {
            return isValidDomain;
        });
    } else {
        root.isValidDomain = isValidDomain;
    }

})(this);
