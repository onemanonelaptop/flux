var flux = {
    init: function () {
        "use strict";
        // initialize
        flux.fieldset.init();
        flux.metaboxes();
        flux.multigroup.init();
        flux.attachments.init();
        // bind everything for the first time
        flux.rebind();
    },
    rebind: function () {
        "use strict";
        // bind the unbound
        flux.fieldset.bind();
        flux.suggest();
        flux.datepicker();
        flux.attachments.bind();
        flux.map();
    },
    map: function () {
        "use strict";
        // for each div with the class of gmap
        jQuery(".gmap").each(function (index) {
            var map = [],
                savedlat = jQuery("[name=\"" + jQuery(this).data("latfield") + "\"]").val(),
                savedlong = jQuery("[name=\"" + jQuery(this).data("longfield") + "\"]").val(),
                latlng = new google.maps.LatLng(savedlat, savedlong),
                options = {
                    zoom: jQuery(this).data("zoom"),
                    center: latlng,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    draggableCursor: "crosshair",
                    streetViewControl: false
                },
                marker;

            map[index] = new google.maps.Map(document.getElementById(jQuery(this).attr("id")), options);

            // stick the map marker on the map
            marker = new google.maps.Marker({
                position: latlng,
                map: map[index]
            });
            // add the map clickerooner
            map[index].latfield = jQuery(this).data("latfield");
            map[index].longfield = jQuery(this).data("longfield");

            google.maps.event.addListener(map[index], "click", function (location) {
                if (marker !== null) {
                    marker.setMap(null);
                }
                marker = new google.maps.Marker({
                    position: location.latLng,
                    map: map[index]
                });

                jQuery("[name=\"" + map[index].latfield + "\"]").val(location.latLng.lat());
                jQuery("[name=\"" + map[index].longfield + "\"]").val(location.latLng.lng());
            });
        });
    },
    multigroup: {
        init: function () {
            "use strict";
            jQuery(".multigroup-sortable").sortable({
                update: function (event, ui) {
                    flux.multigroup.redorder(this);
                }
            });

            jQuery("body").on("click", ".form-type-another", function (event) {
                // dont do what you were going to do
                event.preventDefault();
                // find the outer wrapper
                var wrapper = jQuery(this).closest(".multigroup-wrapper"),
                    controller = wrapper.find('.multigroup-controller'),
                    newgroup = jQuery(wrapper).find(".multigroup:first").clone();
                // reset some values
                newgroup.find(".form-field:not([type=checkbox],[type=radio])").attr("value", "").attr("checked", false);
                newgroup.find(".form-field[type=radio]:first").attr("checked", true);
                // for each element in the group
                newgroup.find(".form-field").each(function (index) {

                    // Date picker gives the input field an id so we must remove it here
                    if (jQuery(this).hasClass("dated")) {
                        jQuery(this).attr("id", "");
                    }

                    // remove the classes so the new fields get rebound with handlers
                    jQuery(this).removeClass("suggested picked hasDatepicker dated");

                    // Change the field index to a high number temporarily so that we can insert it before field reordering
                    jQuery(this).attr("name",
                        jQuery(this).attr("name").replace(/\[([0-9]*)\]\[/, "[9999999][")
                        );
                });

                controller.append(newgroup);
                flux.multigroup.reorder(controller);
            });

            jQuery('body').find('.multigroup-controller').each(function () {
                flux.multigroup.reorder(this);
            });

            // if the delete group button is pressed
            jQuery("body").on("click", ".form-type-delete", function (event) {
                event.preventDefault();
                // Save a reference to the outer wrapper	
                var wrapper = jQuery(this).closest(".multigroup-wrapper");
                // remove the one we want to delete
                jQuery(this).closest(".multigroup").remove();
                // Reset the field ordering
                flux.multigroup.reorder(wrapper);
            });

        },
        reorder: function (controller) {
            "use strict";
            // Save the max allowed values
            var max = jQuery(controller).data("max"),
                fieldcount = jQuery(controller).find(".multigroup").length; // How many fields do we already have

            // Remove all the delete buttons
            jQuery(controller).find(".form-type-delete").remove();

            jQuery(controller).find(".multigroup").each(function (index) {
                if (index % 2 === 0) {
                    jQuery(this).addClass('even').removeClass('odd');
                } else {
                    jQuery(this).addClass('odd').removeClass('even');
                }
                jQuery(this).attr("data-set", index);
                jQuery(this).find(".form-field").each(function () {
                    jQuery(this).attr("name",
                            jQuery(this).attr("name").replace(/\[([0-9]*)\]\[/, "[" + (index) + "][")
                        );
                });
                // Add the delete buttons back in
                if (index !== 0) { jQuery("<a href=\'#\' class=\'form-type-delete button\'>Delete </a>").appendTo(jQuery(this)); }
            });

            // Remove the add another button
            jQuery(controller).find(".another-group").remove();

            // Add the add another button if needed
            if (fieldcount < max) {
                jQuery(controller).find(".field-group:last").after("<a href=\'#\' class=\'another-group button-primary\'>Add Another</a>");
            }

            flux.rebind();
        }
    },
    attachments: {
        init: function () {
            "use strict";
            flux.attachments.editor();

            jQuery(".form-attachment:not(.suggested)").each(function () {
                jQuery(this).suggest(
                    ajaxurl + "?action=attachments_action&group=" + jQuery(this).data("group") + "&field=" + jQuery(this).data("field"),
                    {
                        onSelect: function () {
                            jQuery(this).parent().find("img").attr("src", this.value);
                            jQuery(this).focus();
                        }
                    }
                ).addClass("suggested");
            });

            // add the data transfer prop
            jQuery.event.props.push("dataTransfer");
        },
        bind: function () {
            "use strict";

            // for any unbound attachment fields
            jQuery(".form-attachment:not(.bound)").each(function (index, domEle) {
                // Make its container a droppable area
                jQuery(this).parent().bind("dragenter dragover", false).bind("drop", function (evt) {
                    if (typeof FormData === "undefined") { return; }
                    // dont do whatever you were going to do 
                    evt.stopPropagation();
                    evt.preventDefault();

                    // Add a class so we know weve already bound this element
                    jQuery(this).addClass("bound");

                    var i = jQuery(this).find("img"),
                        a = jQuery(this).find(".form-attachment"),
                        files = evt.dataTransfer.files,
                        file = files[0];

                    if (files.length > 0) {
                        jQuery(a).val("Uploading... please wait");
                        jQuery(a).addClass("ajax");
                        var fd = new FormData();
                        fd.append("upload", files[0]);

                        // ajax call (save_attachment_action)
                        jQuery.ajax({
                            url: "/wp-admin/admin-ajax.php?action=save_attachment_action",
                            data: fd,
                            cache: false,
                            contentType: false,
                            processData: false,
                            type: "POST",
                            success: function (data) {
                                jQuery(a).val(data);
                                jQuery(a).removeClass("ajax");
                                jQuery(a).focus();
                            }
                        });

                        if (typeof FileReader !== "undefined" && file.type.indexOf("image") !== -1) {
                            var reader = new FileReader();

                            reader.onload = function (evt) {
                                // add the image data to the image preview
                                jQuery(i).attr("src", evt.target.result);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            });
        },
        editor: function () {
            "use strict";
            //Overide the send_to_editor function
            var _send_to_editor = window.send_to_editor;
            window.send_to_editor = function (html) {

                var imgurl, aurl;
                if (jQuery(".form-attachment.active").length > 0) {
//                  frames['TB_iframeContent344'].document.getElementById('send[123]').value = 'Use this attachment';
                    imgurl = jQuery("img", html).attr("src");
                    aurl = jQuery("a", "<div>" + html + "</div>").attr("href");

                    if (imgurl) {
                        jQuery(".form-attachment.active").val(imgurl);
                    } else {
                        jQuery(".form-attachment.active").val(aurl);
                    }
                    jQuery(".form-attachment").removeClass("active");
                    tb_remove();
                } else {
                    _send_to_editor(html);
                }
            };
        }
    },
    colorpicker: function () {
        "use strict";
        var colorpickers = jQuery("input.form-color:not(.picked)");

        colorpickers.each(function () {
            var pickerid = jQuery(this);
            jQuery(this).next("div.picker").farbtastic(function (color) {
                pickerid.val(color.toUpperCase())
                    .prev(".swatch")
                    .css("background", color);
            });
        }).addClass("picked");

        // Show and hide the picker 
        colorpickers.focus(function () { jQuery(this).next("div.picker").show(); });
        colorpickers.blur(function () { jQuery(this).next("div.picker").hide(); });
    },
    datepicker: function () {
        "use strict";
        jQuery(".form-date:not(.dated)").each(function () {
            // Do the date picker using HTML5 data atrributes
            jQuery(this).datepicker({
                defaultDate: "0",
                numberOfMonths: jQuery(this).data("numberofmonths"),
                showOtherMonths: jQuery(this).data("showothermonths"),
                dateFormat: jQuery(this).data("dateformat")
            }).addClass("dated");
        });
    },
    suggest: function () {
        "use strict";
        // Apply jquery suggest to textboxes with class .form-suggest
        jQuery(".form-suggest:not(.suggested)").each(function () {
            jQuery(this).suggest(ajaxurl + "?action=suggest_action&group=" + jQuery(this).attr('name'))
                .addClass("suggested");
        });
    },
    metaboxes: function () {
        "use strict";

        if (jQuery('form#settings').data('hook')) {
            jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            postboxes.add_postbox_toggles(jQuery('form#settings').data('hook'));
        }

        jQuery('.always-open').each(function () {
            var metabox = jQuery(this).closest('.postbox');
            jQuery('.handlediv', metabox).remove();
            metabox.removeClass('closed'); /* start opened */
            jQuery('.hndle', metabox).css('cursor', 'auto');
            setTimeout(function () {
                jQuery('h3.hndle', metabox).unbind('click');
            }, 1000);
        });

        jQuery('.start-closed').each(function () {
            var metabox = jQuery(this).closest('.postbox');
            metabox.addClass('closed');
        });

        jQuery('.start-opened').each(function () {
            var metabox = jQuery(this).closest('.postbox');
            metabox.removeClass('closed');
        });
    },
    fieldset: {
        fieldsets : {},
        init: function () {
            "use strict";
            jQuery('body').on('click', '.fieldset-title', function (event) {
                event.preventDefault();
                var fieldset = jQuery(this).closest('fieldset');
                // Don't animate multiple times.
                if (!fieldset.animating) {
                    fieldset.animating = true;
                    flux.fieldset.toggleFieldset(fieldset);
                }
            });
        },
        legends: function () {
            "use strict";
        },
        bind: function () {
            "use strict";
            jQuery('fieldset.collapsible:not(.collapsified)').each(function () {
                var $fieldset = jQuery(this),
                    $legend = jQuery('> legend .fieldset-legend', this),
                    $link = jQuery('<a class="fieldset-title" href="#"></a>');

                if (jQuery(this).hasClass('collapsed')) {
                    jQuery('> .fieldset-wrapper', $fieldset).hide();
                }

                // Turn the legend into a clickable link, but retain span.fieldset-legend
                // for CSS positioning.
                $link.prepend($legend.contents())
                    .appendTo($legend);

            }).addClass('collapsified');
        },
        toggleFieldset: function (fieldset) {
            "use strict";
            var $fieldset = jQuery(fieldset);
            if ($fieldset.is('.collapsed')) {
                var $content = jQuery('> .fieldset-wrapper', fieldset).hide();
                $fieldset.removeClass('collapsed')
                    .trigger({type: 'collapsed', value: false});
                $content.slideDown({
                    duration: 'fast',
                    easing: 'linear',
                    complete: function () {
                        fieldset.animating = false;
                    },
                    step: function () {
                        // Scroll the fieldset into view.
                    }
                });
            } else {
                $fieldset.trigger({type: 'collapsed', value: true});
                jQuery('> .fieldset-wrapper', fieldset).slideUp('fast', function () {
                    $fieldset.addClass('collapsed');
                    fieldset.animating = false;
                });
            }
        }
    }
};

jQuery(document).ready(function ($) {
    "use strict";
    // initialize
    flux.init();
});
