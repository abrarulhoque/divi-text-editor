/**
 * Divi Text Editor - Admin JavaScript
 */
;(function ($) {
  'use strict'

  // Create safe console wrapper to prevent errors when console is closed
  var safeConsole = (function () {
    var dummy = function () {}
    var methods = ['log', 'debug', 'info', 'warn', 'error']
    var console = window.console || {}
    var safeConsole = {}

    // Create safe versions of all console methods
    for (var i = 0; i < methods.length; i++) {
      var method = methods[i]
      safeConsole[method] = console[method] || dummy
    }

    return safeConsole
  })()

  // Debug flag - Enable for debugging
  var DEBUG = true

  // Debug logging function with safe console
  function debug () {
    if (DEBUG && arguments.length > 0) {
      safeConsole.log.apply(safeConsole, arguments)
    }
  }

  // Track initialization state
  var isInitialized = false

  // Selection tracking variables
  var selectionActive = false
  var selectionStart = null
  var selectedTextareas = []
  var $textareaContainer = null
  var usingDragHandle = false

  // Main initialization function
  function initialize () {
    // Prevent multiple initializations
    if (isInitialized) return

    try {
      debug('Starting initialization')

      // Initialize text mapping functionality
      if ($('#map_text_button').length) {
        initTextMapping()
      }

      // Initialize multi-cell paste functionality for text settings textareas
      initMultiCellPaste()

      // Initialize drag selection functionality
      initDragSelection()

      // Mark as initialized
      isInitialized = true
      debug('Initialization complete')
    } catch (error) {
      safeConsole.error('Initialization error:', error)
      // Display a user-friendly error message if in admin
      if ($('.wrap').length > 0) {
        $(
          '<div class="notice notice-error"><p>There was an error initializing the Divi Text Editor functionality. Please refresh the page.</p></div>'
        )
          .insertAfter('.wrap h1')
          .first()
      }
    }
  }

  // Use multiple initialization approaches for reliability
  $(document).ready(function () {
    debug('Document ready triggered')
    initialize()
  })

  // Secondary initialization trigger
  $(window).on('load', function () {
    debug('Window load triggered')
    initialize()
  })

  // Fallback initialization with delay
  setTimeout(function () {
    debug('Delayed initialization triggered')
    initialize()
  }, 500)

  /**
   * Initialize Excel-like drag selection functionality
   */
  function initDragSelection () {
    debug('Initializing drag selection functionality')

    $textareaContainer = $('#settings .form-table')

    // Exit if container not found
    if (!$textareaContainer.length) {
      debug('Textarea container not found, skipping drag selection init')
      return
    }

    try {
      // Add drag handles to each textarea
      $textareaContainer.find('textarea').each(function () {
        addDragHandleToTextarea($(this))
      })

      // Track mousedown on textarea drag handles to start selection
      $textareaContainer.on('mousedown', '.textarea-drag-handle', function (e) {
        // Start selection only on left mouse button (0)
        if (e.button !== 0) return

        var $handle = $(this)
        var $textarea = $handle.closest('td').find('textarea')

        // If Ctrl/Cmd key is pressed, continue adding to existing selection
        if (!e.ctrlKey && !e.metaKey) {
          // Clear previous selection
          clearTextareaSelection()
        }

        // Mark the start of selection and set flag that we're using a drag handle
        selectionStart = $textarea
        selectionActive = true
        usingDragHandle = true

        // Add the first textarea to selection
        addTextareaToSelection(selectionStart)

        // Prevent default behavior
        e.preventDefault()
        e.stopPropagation()
      })

      // Track mouseover on textareas during selection
      $textareaContainer.on('mouseover', 'textarea', function () {
        if (selectionActive && selectionStart && usingDragHandle) {
          addTextareaToSelection($(this))
        }
      })

      // End selection on mouseup anywhere in the document
      $(document).on('mouseup', function () {
        if (selectionActive) {
          selectionActive = false
          usingDragHandle = false
          debug(
            'Selection ended. Selected textareas:',
            selectedTextareas.length
          )
        }
      })

      // Track click on textareas to select single textarea
      $textareaContainer.on('click', 'textarea', function (e) {
        // Only handle selection if Ctrl/Cmd is pressed, otherwise let the normal behavior happen
        if (e.ctrlKey || e.metaKey) {
          // Toggle selection of this textarea
          var $textarea = $(this)
          var index = selectedTextareas.indexOf($textarea[0])

          if (index === -1) {
            // Not selected yet, so add it
            addTextareaToSelection($textarea)
          } else {
            // Already selected, so remove it
            selectedTextareas.splice(index, 1)
            $textarea.removeClass('textarea-selected')
          }

          e.preventDefault()
        } else if (!usingDragHandle) {
          // Normal click without Ctrl/Cmd, clear selection
          clearTextareaSelection()
        }
      })

      // Handle Ctrl+C for copying multiple textareas
      $(document).on('keydown', function (e) {
        // Check if Ctrl+C or Cmd+C was pressed
        if (
          (e.ctrlKey || e.metaKey) &&
          e.keyCode === 67 &&
          selectedTextareas.length > 1
        ) {
          copySelectedTextareas()
          e.preventDefault()
        }
      })

      debug('Drag selection initialized successfully')
    } catch (error) {
      safeConsole.error('Error initializing drag selection:', error)
    }
  }

  /**
   * Add a drag handle to a textarea (similar to Excel's cell selection handle)
   */
  function addDragHandleToTextarea ($textarea) {
    var $container = $textarea.parent()

    // Check if handle already exists
    if ($container.find('.textarea-drag-handle').length === 0) {
      // Add relative positioning to container if needed
      if ($container.css('position') !== 'relative') {
        $container.css('position', 'relative')
      }

      // Create and append the drag handle
      var $handle = $(
        '<div class="textarea-drag-handle" title="Click and drag to select multiple fields"></div>'
      )
      $container.append($handle)
    }
  }

  /**
   * Add a textarea to the current selection
   */
  function addTextareaToSelection ($textarea) {
    // Check if this textarea is already selected
    for (var i = 0; i < selectedTextareas.length; i++) {
      if (selectedTextareas[i] === $textarea[0]) {
        return // Already in selection
      }
    }

    // Add visual selection style
    $textarea.addClass('textarea-selected')

    // Add to selection array
    selectedTextareas.push($textarea[0])

    debug('Added textarea to selection:', $textarea.attr('id'))
  }

  /**
   * Clear all selected textareas
   */
  function clearTextareaSelection () {
    // Remove visual selection from all textareas
    $('.textarea-selected').removeClass('textarea-selected')

    // Clear selection array
    selectedTextareas = []

    debug('Selection cleared')
  }

  /**
   * Copy content from all selected textareas
   */
  function copySelectedTextareas () {
    if (selectedTextareas.length === 0) return

    try {
      // Sort textareas by their position in the DOM
      selectedTextareas.sort(function (a, b) {
        // Get all textareas
        var allTextareas = $textareaContainer.find('textarea').toArray()
        return allTextareas.indexOf(a) - allTextareas.indexOf(b)
      })

      // Extract text from each textarea
      var textLines = []
      $(selectedTextareas).each(function () {
        textLines.push($(this).val() || '')
      })

      // Join lines with newline character
      var clipboardText = textLines.join('\n')

      // Use modern clipboard API if available
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(clipboardText)
          .then(function () {
            debug('Copied to clipboard using Clipboard API')
            showMultiPasteMessage(
              'success',
              'Copied ' + selectedTextareas.length + ' fields to clipboard.'
            )
          })
          .catch(function (error) {
            debug('Clipboard API failed, falling back to execCommand')
            fallbackCopyToClipboard(clipboardText)
          })
      } else {
        fallbackCopyToClipboard(clipboardText)
      }
    } catch (error) {
      safeConsole.error('Error during copy operation:', error)
      showMultiPasteMessage('warning', 'Error copying to clipboard.')
    }
  }

  /**
   * Fallback method for copying to clipboard using execCommand
   */
  function fallbackCopyToClipboard (text) {
    // Copy to clipboard using a temporary element
    var $temp = $('<textarea>')
    $('body').append($temp)
    $temp.val(text).select()

    try {
      var success = document.execCommand('copy')
      if (success) {
        debug('Copied to clipboard using execCommand')
        showMultiPasteMessage(
          'success',
          'Copied ' + selectedTextareas.length + ' fields to clipboard.'
        )
      } else {
        debug('Copy command failed')
        showMultiPasteMessage('warning', 'Failed to copy to clipboard.')
      }
    } catch (err) {
      debug('Error copying:', err)
      showMultiPasteMessage('warning', 'Error copying to clipboard.')
    }

    $temp.remove()
  }

  /**
   * Initialize Multi-Cell Paste functionality
   */
  function initMultiCellPaste () {
    debug('Initializing multi-cell paste functionality')

    try {
      // Attach paste event to textareas in the settings tab
      $('#settings').on('paste', 'textarea', function (event) {
        debug('Paste event detected')

        // Get clipboard data
        var clipboardData =
          event.originalEvent.clipboardData || window.clipboardData
        if (!clipboardData) {
          debug('No clipboard data available')
          return
        }

        var pastedData = clipboardData.getData('text')
        if (!pastedData) {
          debug('No text data in clipboard')
          return
        }

        debug('Pasted text length:', pastedData.length)

        // Check if it contains multiple lines (split by different line endings)
        var lines = pastedData.split(/\r\n|\r|\n/).filter(function (line) {
          return line !== '' // Filter out empty lines
        })

        debug('Lines detected:', lines.length)

        // If there's only one line, let the default paste behavior happen
        if (lines.length <= 1) {
          debug('Single line paste - using default behavior')
          return
        }

        // Prevent default paste behavior for multi-line paste
        event.preventDefault()
        debug('Default paste behavior prevented')

        // Get the current textarea's position in the form structure
        var $currentTextarea = $(this)
        debug('Current textarea:', $currentTextarea.attr('id'))

        var $currentRow = $currentTextarea.closest('tr')
        debug('Current row found:', $currentRow.length > 0)

        var $tableBody = $currentRow.parent()
        debug(
          'Table body found:',
          $tableBody.length > 0,
          $tableBody.prop('tagName')
        )

        var $allRows = $tableBody.children('tr')
        debug('All rows count:', $allRows.length)

        var currentRowIndex = $allRows.index($currentRow)
        debug('Current row index:', currentRowIndex)

        // Keep track of modified textareas for feedback
        var modifiedTextareas = []

        // Set the first line to the current textarea
        debug('Setting first line to current textarea')
        $currentTextarea.val(lines[0])
        modifiedTextareas.push($currentTextarea[0])

        // Trigger change event to make sure WordPress captures the change
        $currentTextarea.trigger('change')

        // Process remaining lines and distribute to subsequent textareas
        var linesProcessed = 1 // We've already processed the first line

        // Find next rows and their textareas
        debug('Starting to process remaining lines')
        for (
          var i = currentRowIndex + 1;
          i < $allRows.length && linesProcessed < lines.length;
          i++
        ) {
          var $nextRow = $allRows.eq(i)
          debug('Next row ' + i + ' found:', $nextRow.length > 0)

          var $nextTextarea = $nextRow.find('textarea')
          debug('Next textarea found:', $nextTextarea.length > 0)

          if ($nextTextarea.length) {
            debug('Setting line ' + linesProcessed)
            $nextTextarea.val(lines[linesProcessed])
            $nextTextarea.trigger('change')
            modifiedTextareas.push($nextTextarea[0])
            linesProcessed++
          }
        }

        debug('Total lines processed:', linesProcessed)

        // Provide visual feedback for all modified textareas
        $(modifiedTextareas).each(function () {
          var $textarea = $(this)
          $textarea.addClass('multi-paste-highlight')
          setTimeout(function () {
            $textarea.removeClass('multi-paste-highlight')
          }, 800)
        })

        // Show user feedback message
        if (lines.length > linesProcessed) {
          var message =
            'Some lines were not pasted because there were more lines than available fields.'
          showMultiPasteMessage('warning', message)
        } else {
          showMultiPasteMessage(
            'success',
            'Successfully pasted ' + linesProcessed + ' lines.'
          )
        }
      })

      debug('Multi-cell paste initialized successfully')
    } catch (error) {
      safeConsole.error('Error initializing multi-cell paste:', error)
    }
  }

  /**
   * Show a message for multi-paste operations
   */
  function showMultiPasteMessage (type, message) {
    debug('Showing message:', type, message)

    try {
      // Create message element if it doesn't exist
      if ($('#multi_paste_message').length === 0) {
        $(
          '<div id="multi_paste_message" class="multi-paste-message"></div>'
        ).insertBefore('#settings .form-table')
      }

      var $message = $('#multi_paste_message')

      // Set message and class
      $message
        .removeClass('success warning')
        .addClass(type)
        .html(message)
        .show()

      // Hide after 5 seconds
      setTimeout(function () {
        $message.fadeOut(500)
      }, 5000)
    } catch (error) {
      safeConsole.error('Error showing message:', error)
    }
  }

  /**
   * Initialize Text Mapping functionality
   */
  function initTextMapping () {
    debug('Initializing text mapping functionality')

    try {
      $('#map_text_button').on('click', function (e) {
        e.preventDefault()

        var text = $('#text_to_map').val()
        var variable = $('#variable_to_map').val()

        // Validate inputs
        if (!text) {
          showMappingMessage('error', 'Please enter the text to map.')
          return
        }

        if (!variable) {
          showMappingMessage(
            'error',
            'Please select a variable to map the text to.'
          )
          return
        }

        // Show spinner
        var $spinner = $('.text-mapping-actions .spinner')
        $spinner.css('visibility', 'visible')

        // Send AJAX request
        $.ajax({
          url: diviTextEditor.ajaxUrl,
          type: 'POST',
          data: {
            action: 'divi_text_editor_map_text',
            nonce: diviTextEditor.nonce,
            text: text,
            variable: variable
          },
          success: function (response) {
            $spinner.css('visibility', 'hidden')

            if (response.success) {
              showMappingMessage('success', diviTextEditor.mapping_success)

              // Update the textarea in the settings tab if it exists
              var $settingTextarea = $('#divi_text_editor_' + variable)
              if ($settingTextarea.length) {
                $settingTextarea.val(text)
              }

              // Clear the text field
              $('#text_to_map').val('')
            } else {
              showMappingMessage(
                'error',
                response.data.message || diviTextEditor.mapping_error
              )
            }
          },
          error: function () {
            $spinner.css('visibility', 'hidden')
            showMappingMessage('error', diviTextEditor.mapping_error)
          }
        })
      })

      debug('Text mapping initialized successfully')
    } catch (error) {
      safeConsole.error('Error initializing text mapping:', error)
    }
  }

  /**
   * Show a mapping result message
   */
  function showMappingMessage (type, message) {
    try {
      var $result = $('#mapping_result')

      $result.removeClass('success error').addClass(type)
      $result.html(message)
      $result.show()

      // Hide after 5 seconds
      setTimeout(function () {
        $result.fadeOut(500)
      }, 5000)
    } catch (error) {
      safeConsole.error('Error showing mapping message:', error)
    }
  }

  // Global error handler
  window.addEventListener('error', function (event) {
    if (DEBUG) {
      // Log error to console safely
      safeConsole.error(
        'JavaScript error caught:',
        event.message,
        'at',
        event.filename,
        ':',
        event.lineno
      )

      // Only show in admin area and only once
      if ($('.wrap').length > 0 && !window.errorAlreadyShown) {
        window.errorAlreadyShown = true
        $(
          '<div class="notice notice-error is-dismissible"><p>A JavaScript error occurred. Please check the browser console for details.</p></div>'
        )
          .insertAfter('.wrap h1')
          .first()
      }
    }
  })
})(jQuery)
