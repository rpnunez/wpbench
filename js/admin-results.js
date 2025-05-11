jQuery(document).ready(function($) {
    // Ensure Chart.js and our data object are available
    if (typeof Chart === 'undefined') {
        console.error("WPBench Error: Chart.js library not loaded.");
        $('#wpbenchTimingChart, #wpbenchMemoryChart').parent().append('<p style="color:red;">Error: Charting library failed to load.</p>');
        return;
    }
     if (typeof wpbench_results_data === 'undefined') {
        console.error("WPBench Error: Results data object not found.");
         $('#wpbenchTimingChart, #wpbenchMemoryChart').parent().append('<p style="color:red;">Error: Benchmark data not available to JavaScript.</p>');
        return;
    }

    var results = wpbench_results_data.results || {};
    var selectedTests = wpbench_results_data.selected_tests || []; // Get selected tests
    var score = wpbench_results_data.score; // Get score (can be null)
    var textLabels = wpbench_results_data.text || {};
    var hasError = false; // Flag if any component has an error message

    // Helper function to safely get data or default, checking for errors
    // Returns null if test wasn't selected or data for the key is missing
    function getResultData(component, key, defaultValue = 0) {
        // Check if the test was selected AND results exist for it AND the specific key exists
        if (selectedTests.includes(component) && results[component] && results[component][key] !== undefined && results[component][key] !== null) {
            // Mark global error flag if component has one
            if(results[component].error) {
                hasError = true;
            }

            return results[component][key];
        }

        // Return null if test wasn't run or data missing, to potentially skip plotting
        return null;
    }

    // Helper function to get error message if test was selected and has an error
    function getResultError(component) {
        return (selectedTests.includes(component) && results[component] && results[component].error) ? results[component].error : null;
    }

    // --- Score Chart (Doughnut) ---
    var scoreCtx = document.getElementById('wpbenchScoreChart');

    if (scoreCtx && score !== null && score >= 0 && score <= 100) {
        try {
            // Determine color based on score range (Example thresholds)
            var scoreColor = '#E0E0E0'; // Default grey if calculation weirdly out of range

            if (score > 75) {
                scoreColor = '#4CAF50'; // Green
            } else if (score > 40) {
                scoreColor = '#FFC107'; // Amber/Orange
            } else {
                scoreColor = '#F44336'; // Red
            }

            var remainingColor = '#f5f5f5'; // Light grey for remaining part

            // Custom plugin to draw text in the center
            const centerTextPlugin = {
                id: 'wpbenchCenterText',
                afterDraw: function(chart) {
                    if (chart.config.options.plugins?.centerText?.display) {
                        let ctx = chart.ctx;
                        let centerConfig = chart.config.options.plugins.centerText;
                        let fontStyle = centerConfig.fontStyle || 'Arial';
                        let txt = centerConfig.text;
                        let color = centerConfig.color || '#000';
                        let maxFontSize = centerConfig.maxFontSize || 75;
                        let minFontSize = centerConfig.minFontSize || 20;
                        let fontRatio = centerConfig.fontRatio || 0.4; // Ratio of inner radius for font size

                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        let centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                        let centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

                        // Calculate font size based on inner radius
                        let innerRadius = chart.innerRadius;
                        // Use a fixed proportion of the inner radius, clamped
                        let fontSize = Math.max(minFontSize, Math.min(maxFontSize, innerRadius * fontRatio));

                        ctx.font = fontSize + "px " + fontStyle;
                        ctx.fillStyle = color;

                        // Draw the text ('75%')
                        ctx.fillText(txt, centerX, centerY);
                        ctx.restore();
                    }
                }
            };

            var scoreChart = new Chart(scoreCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [score, 100 - score], // Score value, Remaining value
                        backgroundColor: [
                            scoreColor,       // Color for the score segment
                            remainingColor    // Color for the remainder
                        ],
                        borderColor: [ // Optional: Add border for separation
                            '#fff', // White border
                            '#fff'
                        ],
                        borderWidth: 2, // Border width
                        circumference: 360, // Full circle
                        rotation: -90 // Start at the top (12 o'clock)
                    }]
                    // No labels needed: labels: ['Score', 'Remaining']
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true, // Keep aspect ratio within the container
                    cutout: '75%', // Adjust thickness of the doughnut ring
                    plugins: {
                        legend: { display: false }, // Hide default legend
                        tooltip: { enabled: false }, // Disable tooltips for score chart
                        // Configure custom center text plugin
                        centerText: {
                            display: true,
                            text: score + '%', // Text to display
                            color: scoreColor, // Use the score color for text too
                            fontStyle: 'bold sans-serif', // Font style
                            fontRatio: 0.45, // Adjust size relative to inner radius
                            minFontSize: 16,
                            maxFontSize: 40
                        }
                    },
                    animation: {
                        duration: 1000, // Animation duration in ms
                        easing: 'easeOutBounce' // Example easing
                    }
                },
                plugins: [centerTextPlugin] // Register the custom plugin instance
            });

            // Hide the static overlay text now that JS chart is rendered
            $('.wpbench-score-text').hide();
        } catch(e) {
            console.error("WPBench Error creating score chart:", e);
            $(scoreCtx).parent().append('<p style="color:red;">Error: Could not display score chart.</p>');
            $('.wpbench-score-text').show(); // Show fallback text on error
        }
    } else if (scoreCtx) {
        // Score is null or invalid, maybe hide canvas or show 'N/A' via static HTML (already handled)
        console.warn("WPBench: Score data invalid or missing, score chart not rendered.");

        // Ensure static text remains visible if needed
        $('.wpbench-score-text').show();
    }

    // --- Timing Chart ---
    var timingCtx = document.getElementById('wpbenchTimingChart');

    if (timingCtx) {
         try {
            // Dynamically build labels and data based on *selected* tests that have results
            const timingLabels = [];
            const timingDataValues = [];
            const backgroundColors = [];
            const borderColors = [];
            const timeTestOrder = ['cpu', 'file_io', 'db_read', 'db_write']; // Define which tests belong in the timing chart
            const colorMap = { // Map test IDs to colors
                'cpu': { bg: 'rgba(255, 99, 132, 0.6)', border: 'rgba(255, 99, 132, 1)' },
                'file_io': { bg: 'rgba(54, 162, 235, 0.6)', border: 'rgba(54, 162, 235, 1)' },
                'db_read': { bg: 'rgba(255, 206, 86, 0.6)', border: 'rgba(255, 206, 86, 1)' },
                'db_write': { bg: 'rgba(75, 192, 192, 0.6)', border: 'rgba(75, 192, 192, 1)' },
            };
            const labelMap = {
                 'cpu': textLabels.cpu_time || 'CPU Time (s)',
                 'file_io': textLabels.file_io_time || 'File I/O Time (s)',
                 'db_read': textLabels.db_read_time || 'DB Read Time (s)',
                 'db_write': textLabels.db_write_time || 'DB Write Time (s)',
            };

            hasError = false; // Reset error flag specifically for this chart

            // Iterate through the tests relevant to this chart
            for (const testId of timeTestOrder) {
                 const timeValue = getResultData(testId, 'time', null); // Get time, default null if not run/no data

                 if (timeValue !== null) { // Only include if test ran and has time data
                     const error = getResultError(testId);
                     timingLabels.push(labelMap[testId] + (error ? ' (!)' : ''));
                     timingDataValues.push(timeValue);
                     backgroundColors.push(colorMap[testId]?.bg || 'rgba(201, 203, 207, 0.6)'); // Default grey
                     borderColors.push(colorMap[testId]?.border || 'rgba(201, 203, 207, 1)');
                     if(error) hasError = true; // Mark error if this component had one
                 }
             }

             // Check if we actually have data to plot
             if(timingDataValues.length === 0) {
                 $(timingCtx).parent().append('<p>No timing test results available for this chart (tests might not have been selected or failed early).</p>');
                 $(timingCtx).remove(); // Remove the canvas if no data
             } else {
                 // Prepare final data structure for Chart.js
                 const timingData = {
                    labels: timingLabels,
                    datasets: [{
                        label: textLabels.benchmark_results || 'Benchmark Results (seconds)',
                        data: timingDataValues,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                 };

                 // Create the chart instance
                 var timingChart = new Chart(timingCtx.getContext('2d'), {
                     type: 'bar',
                     data: timingData,
                     options: {
                         scales: {
                             y: {
                                 beginAtZero: true,
                                 title: {
                                      display: true,
                                      text: 'Time (seconds)'
                                 }
                             }
                         },
                         plugins: {
                             title: {
                                 display: true,
                                 text: 'Test Component Timings' + (hasError ? ' (Note: Some tests had errors)' : '')
                             },
                              legend: {
                                 display: false // Hide legend as labels are clear
                             },
                             tooltip: {
                                  callbacks: {
                                     label: function(context) {
                                         let label = context.dataset.label || '';
                                         if (label) { label += ': '; }
                                         if (context.parsed.y !== null) {
                                             label += context.parsed.y + ' s';
                                         }
                                         // Add error message to tooltip if exists for this bar
                                         let error = null;
                                         // Determine test ID based on label text (a bit fragile, relies on label format)
                                         const labelText = context.label;
                                         if(labelText.includes(labelMap['cpu'])) error = getResultError('cpu');
                                         else if(labelText.includes(labelMap['file_io'])) error = getResultError('file_io');
                                         else if(labelText.includes(labelMap['db_read'])) error = getResultError('db_read');
                                         else if(labelText.includes(labelMap['db_write'])) error = getResultError('db_write');

                                         if(error) {
                                             // Basic tooltip formatting for multi-line
                                             const maxLineLength = 40;
                                             let errorLines = [];
                                             let currentLine = 'Error: ';
                                             error.split(' ').forEach(word => {
                                                 if ((currentLine + word).length > maxLineLength) {
                                                     errorLines.push(currentLine.trim());
                                                     currentLine = word + ' ';
                                                 } else {
                                                     currentLine += word + ' ';
                                                 }
                                             });
                                             errorLines.push(currentLine.trim());
                                             label += '\n' + errorLines.join('\n'); // Add error lines to tooltip
                                         }

                                         return label;
                                     }
                                 }
                             }
                         }
                     }
                 });
             }
         } catch (e) {
             console.error("WPBench Error creating timing chart:", e);
             $(timingCtx).parent().append('<p style="color:red;">Error: Could not display timing chart. '+(e.message || '')+'</p>');
         }
    } else {
         console.warn("WPBench: Canvas element #wpbenchTimingChart not found.");
    }

    // --- Memory Chart ---
    var memoryCtx = document.getElementById('wpbenchMemoryChart');
     if (memoryCtx) {
        // Check if memory test was selected AND has results
        const memoryValue = getResultData('memory', 'peak_usage_mb', null);
        const memoryError = getResultError('memory');

        if (memoryValue !== null) { // Only proceed if memory test ran and has data
            try {
                 const memoryLabel = (textLabels.memory_peak || 'Peak Memory (MB)') + (memoryError ? ' (!)' : '');
                 const memoryData = {
                     labels: [memoryLabel],
                     datasets: [{
                         label: textLabels.memory_peak || 'Peak Memory (MB)',
                         data: [memoryValue],
                         backgroundColor: ['rgba(153, 102, 255, 0.6)'], // Purple
                         borderColor: ['rgba(153, 102, 255, 1)'],
                         borderWidth: 1,
                         barThickness: 50 // Make the single bar reasonably thick
                     }]
                 };

                 // Create the chart instance
                 var memoryChart = new Chart(memoryCtx.getContext('2d'), {
                      type: 'bar',
                      data: memoryData,
                      options: {
                          indexAxis: 'y', // Make it horizontal for single value
                          scales: {
                              x: { // Note: x-axis for horizontal bar
                                  beginAtZero: true,
                                  title: {
                                      display: true,
                                      text: 'Memory (MB)'
                                  }
                              },
                              y: { // Hide y-axis labels as it's just one category
                                  ticks: { display: false }
                              }
                          },
                          plugins: {
                              title: {
                                  display: true,
                                  text: 'Peak Memory Usage During Test' + (memoryError ? ' (Note: Test had an error)' : '')
                              },
                              legend: {
                                  display: false
                              },
                              tooltip: {
                                  callbacks: {
                                      label: function(context) {
                                          let label = context.dataset.label || '';
                                          if (label) { label += ': '; }
                                          if (context.parsed.x !== null) {
                                              label += context.parsed.x + ' MB';
                                          }
                                           if(memoryError) {
                                                // Basic tooltip formatting for multi-line
                                                const maxLineLength = 40;
                                                let errorLines = [];
                                                let currentLine = 'Error: ';
                                                memoryError.split(' ').forEach(word => {
                                                    if ((currentLine + word).length > maxLineLength) {
                                                        errorLines.push(currentLine.trim());
                                                        currentLine = word + ' ';
                                                    } else {
                                                        currentLine += word + ' ';
                                                    }
                                                });
                                                errorLines.push(currentLine.trim());
                                                label += '\n' + errorLines.join('\n'); // Add error lines to tooltip
                                           }
                                          return label;
                                      }
                                  }
                              }
                          }
                      }
                 });
             } catch (e) {
                 console.error("WPBench Error creating memory chart:", e);
                 $(memoryCtx).parent().append('<p style="color:red;">Error: Could not display memory chart. '+(e.message || '')+'</p>');
             }
        } else {
            // Memory test was not run or had no results, indicate this
            $(memoryCtx).parent().append('<p>Memory test was not selected or no results are available.</p>');
            $(memoryCtx).remove(); // Remove the canvas element as it won't be used
        }
     } else {
        console.warn("WPBench: Canvas element #wpbenchMemoryChart not found.");
    }

}); // End jQuery ready