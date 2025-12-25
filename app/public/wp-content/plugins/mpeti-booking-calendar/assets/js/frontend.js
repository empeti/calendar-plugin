(function( wp, MBCBooking ) {
	'use strict';
	
	// Suppress unhandled promise rejection warnings from browser extensions
	if (typeof window !== 'undefined') {
		window.addEventListener('unhandledrejection', function(event) {
			const reason = event.reason;
			if (reason) {
				const message = reason.message || reason.toString() || '';
				const stack = reason.stack || '';
				
				if (
					message.includes('storage') ||
					message.includes('Access to storage') ||
					message.includes('not allowed from this context') ||
					stack.includes('content.js') ||
					stack.includes('extension') ||
					stack.includes('chrome-extension://')
				) {
					event.preventDefault();
					event.stopPropagation();
					return false;
				}
			}
		}, true);
		
		const originalError = window.onerror;
		window.onerror = function(message, source, lineno, colno, error) {
			if (
				message && (
					message.includes('storage') ||
					message.includes('Access to storage') ||
					message.includes('not allowed from this context')
				) ||
				source && source.includes('content.js')
			) {
				return true;
			}
			
			if (originalError) {
				return originalError.apply(this, arguments);
			}
			return false;
		};
	}

	// Check if required dependencies exist
	if (typeof wp === 'undefined' || !wp.apiFetch) {
		console.error('mpeti Booking Calendar: WordPress API Fetch is not available');
		return;
	}

	if (typeof MBCBooking === 'undefined') {
		console.error('mpeti Booking Calendar: MBCBooking object is not defined');
		return;
	}

	const apiFetch = wp.apiFetch;

	const calendarEl = document.getElementById('mbc-booking-calendar');
	const formEl = document.querySelector('.mbc-booking-form');
	const messageEl = document.querySelector('.mbc-form-message');
	const serviceSelect = document.getElementById('mbc-service-select');
	const staffListEl = document.getElementById('mbc-available-staff-list');
	const timeSlotsContainer = document.getElementById('mbc-time-slots-container');
	const timeSlotsGrid = document.getElementById('mbc-time-slots-grid');
	
	let currentMonth = new Date().getMonth();
	let currentYear = new Date().getFullYear();
	let selectedService = null;
	let selectedDate = null;
	let selectedStaff = null;
	let selectedStaffName = null;
	let selectedTime = null;
	const availabilityCache = {};

	// Step 1: Service Selection
	function initServiceSelection() {
		const serviceGrid = document.getElementById('mbc-service-grid');
		if (!serviceGrid && !serviceSelect) return;

		// Handle service box clicks
		if (serviceGrid) {
			serviceGrid.querySelectorAll('.mbc-service-card').forEach(card => {
				card.addEventListener('click', function() {
					const serviceId = parseInt(this.dataset.serviceId, 10);
					
					// Update visual selection
					serviceGrid.querySelectorAll('.mbc-service-card').forEach(c => {
						c.classList.remove('selected');
					});
					this.classList.add('selected');
					
					// Update hidden input
					if (serviceSelect) {
						serviceSelect.value = serviceId;
					}
					
					// Set selected service
					selectedService = serviceId;
					
					// Reset other selections first
					resetSelections();
					
					// Enable calendar
					if (calendarEl) {
						calendarEl.classList.remove('mbc-disabled');
						
						// Clear ALL availability classes from calendar before updating
						const grid = calendarEl.querySelector('.mbc-calendar-grid');
						if (grid) {
							grid.querySelectorAll('.mbc-calendar-day').forEach(dayEl => {
								dayEl.classList.remove('is-available-day', 'mbc-has-slots', 'no-slots-available', 'mbc-no-slots');
								dayEl.removeAttribute('role');
								dayEl.removeAttribute('tabindex');
								dayEl.removeAttribute('data-handler-attached');
							});
						}
						
						// Clear ALL cache entries (not just current service) to prevent showing wrong availability
						Object.keys(availabilityCache).forEach(key => delete availabilityCache[key]);
						
						// Update availability immediately and after a short delay to ensure calendar is ready
						updateCalendarAvailability();
						setTimeout(() => {
							updateCalendarAvailability();
						}, 200);
					}
				});
			});
		}

		// Fallback: Handle select change (if select exists)
		if (serviceSelect) {
			serviceSelect.addEventListener('change', function() {
				selectedService = this.value ? parseInt(this.value, 10) : null;
				
				if (selectedService) {
					// Update visual selection
					if (serviceGrid) {
						serviceGrid.querySelectorAll('.mbc-service-card').forEach(card => {
							card.classList.remove('selected');
							if (parseInt(card.dataset.serviceId, 10) === selectedService) {
								card.classList.add('selected');
							}
						});
					}
					
					// Reset other selections first
					resetSelections();
					
					// Enable calendar
					if (calendarEl) {
						calendarEl.classList.remove('mbc-disabled');
						
						// Clear ALL availability classes from calendar before updating
						const grid = calendarEl.querySelector('.mbc-calendar-grid');
						if (grid) {
							grid.querySelectorAll('.mbc-calendar-day').forEach(dayEl => {
								dayEl.classList.remove('is-available-day', 'mbc-has-slots', 'no-slots-available', 'mbc-no-slots');
								dayEl.removeAttribute('role');
								dayEl.removeAttribute('tabindex');
								dayEl.removeAttribute('data-handler-attached');
							});
						}
						
						// Clear ALL cache entries (not just current service) to prevent showing wrong availability
						Object.keys(availabilityCache).forEach(key => delete availabilityCache[key]);
						
						// Update availability immediately and after a short delay to ensure calendar is ready
						updateCalendarAvailability();
						setTimeout(() => {
							updateCalendarAvailability();
						}, 200);
					}
				} else {
					// Reset other selections
					resetSelections();
					// Disable calendar
					if (calendarEl) {
						calendarEl.classList.add('mbc-disabled');
						// Mark all days as not available
						const grid = calendarEl.querySelector('.mbc-calendar-grid');
						if (grid) {
							const today = new Date();
							today.setHours(0, 0, 0, 0);
							grid.querySelectorAll('.mbc-calendar-day:not(.mbc-past):not(.mbc-other-month)').forEach(dayEl => {
								const dateStr = dayEl.dataset.date;
								if (dateStr) {
									// Parse date string as local date (YYYY-MM-DD)
									const [year, month, day] = dateStr.split('-').map(Number);
									const dateObj = new Date(year, month - 1, day);
									dateObj.setHours(0, 0, 0, 0);
									if (dateObj >= today) {
										dayEl.classList.add('no-slots-available', 'mbc-no-slots');
										dayEl.classList.remove('is-available-day', 'mbc-has-slots');
										dayEl.style.cursor = 'not-allowed';
									}
								}
							});
						}
					}
				}
			});
		}

		// Initialize if service is pre-selected
		if (serviceSelect && serviceSelect.value) {
			selectedService = parseInt(serviceSelect.value, 10);
			if (serviceGrid) {
				serviceGrid.querySelectorAll('.mbc-service-card').forEach(card => {
					if (parseInt(card.dataset.serviceId, 10) === selectedService) {
						card.classList.add('selected');
					}
				});
			}
			if (calendarEl) {
				calendarEl.classList.remove('mbc-disabled');
				updateCalendarAvailability();
			}
		}
	}

	// Step 2: Date Selection
	function handleDateClick(date) {
		if (!selectedService) {
			message('Please select a service first', 'error');
			return;
		}

		selectedDate = date;
		
		// Clear previous selections
		selectedStaff = null;
		selectedStaffName = null;
		selectedTime = null;
		
		// Reset visual selections
		// Clear staff selection
		if (staffListEl) {
			staffListEl.querySelectorAll('.mbc-staff-card').forEach(card => {
				card.classList.remove('selected');
			});
		}
		
		// Clear timeslot selection and hide container
		if (timeSlotsContainer) {
			timeSlotsContainer.style.display = 'none';
		}
		if (timeSlotsGrid) {
			timeSlotsGrid.innerHTML = '';
			timeSlotsGrid.querySelectorAll('.mbc-slot').forEach(slot => {
				slot.classList.remove('is-selected');
			});
		}
		
		// Hide selected appointment details
		const selectedAppointmentEl = document.getElementById('mbc-selected-appointment');
		if (selectedAppointmentEl) {
			selectedAppointmentEl.style.display = 'none';
		}
		
		// Hide booking form
		if (formEl) {
			formEl.style.display = 'none';
		}
		
		// Update calendar selection
		if (calendarEl) {
			calendarEl.querySelectorAll('.mbc-calendar-day').forEach(el => {
				el.classList.remove('is-selected');
			});
			const clickedDay = calendarEl.querySelector(`[data-date="${date}"]`);
			if (clickedDay) {
				clickedDay.classList.add('is-selected');
			}
		}

		// Load available staff for this service and date
		loadAvailableStaff(selectedService, date);
	}

	// Step 3: Load Available Staff
	function loadAvailableStaff(serviceId, date) {
		if (!staffListEl) {
			console.error('Staff list element not found');
			return;
		}

		const staffStep = document.querySelector('.mbc-step-staff');
		if (!staffStep) {
			console.error('Staff step element not found');
			return;
		}

		staffListEl.innerHTML = '<p>Loading available staff...</p>';
		staffStep.style.display = 'block';

		apiFetch({
			path: `/mpeti-booking-calendar/v1/available-staff?service=${serviceId}&date=${date}`,
			headers: { 'X-WP-Nonce': MBCBooking.restNonce },
		})
		.then((res) => {
			console.log('Available staff response:', res);
			if (res && res.staff && Array.isArray(res.staff) && res.staff.length > 0) {
				renderStaffList(res.staff);
			} else {
				staffListEl.innerHTML = '<p class="mbc-no-staff">No staff members available for this service on the selected date.</p>';
			}
		})
		.catch((err) => {
			console.error('Error fetching staff:', err);
			staffListEl.innerHTML = '<p class="mbc-error">Error loading staff members. Please try again.</p>';
		});
	}

	// Step 4: Render Staff List
	function renderStaffList(staff) {
		if (!staffListEl) return;

		const defaultAvatar = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#e0e0e0"/><circle cx="16" cy="12" r="6" fill="#999"/><path d="M16 20c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z" fill="#999"/></svg>');

		staffListEl.innerHTML = '';
		const staffGrid = document.createElement('div');
		staffGrid.className = 'mbc-staff-grid';

		staff.forEach((member) => {
			const staffCard = document.createElement('div');
			staffCard.className = 'mbc-staff-card';
			staffCard.dataset.staffId = member.id;
			
			const avatar = document.createElement('img');
			avatar.className = 'mbc-staff-card-avatar';
			avatar.src = member.photo || defaultAvatar;
			avatar.alt = member.name;
			
			const name = document.createElement('div');
			name.className = 'mbc-staff-card-name';
			name.textContent = member.name;
			
			staffCard.appendChild(avatar);
			staffCard.appendChild(name);
			
			// Add hover popup if services exist
			if (member.services && member.services.length > 0) {
				const popup = document.createElement('div');
				popup.className = 'mbc-staff-popup';
				
				const servicesList = member.services.map(service => 
					`<li>${service.name}</li>`
				).join('');
				
				popup.innerHTML = `
					<div class="mbc-staff-popup-content">
						<div class="mbc-staff-popup-header">
							<img src="${member.photo || defaultAvatar}" alt="${member.name}" class="mbc-staff-popup-avatar" />
							<div class="mbc-staff-popup-info">
								<h4 class="mbc-staff-popup-name">${member.name}</h4>
								<div class="mbc-staff-popup-services">
									<h5 class="mbc-staff-popup-services-title">Services:</h5>
									<ul class="mbc-staff-popup-services-list">${servicesList}</ul>
								</div>
							</div>
						</div>
					</div>
				`;
				staffCard.appendChild(popup);
				
				staffCard.addEventListener('mouseenter', () => {
					popup.style.display = 'block';
				});
				
				staffCard.addEventListener('mouseleave', () => {
					popup.style.display = 'none';
				});
			}
			
			staffCard.addEventListener('click', () => {
				selectStaff(member.id, member.name);
			});
			
			staffGrid.appendChild(staffCard);
		});

		staffListEl.appendChild(staffGrid);
	}

	// Step 5: Select Staff
	function selectStaff(staffId, staffName) {
		selectedStaff = parseInt(staffId, 10);
		selectedStaffName = staffName;
		
		// Clear timeslot selection
		selectedTime = null;
		
		// Hide appointment details box
		const selectedAppointmentEl = document.getElementById('mbc-selected-appointment');
		if (selectedAppointmentEl) {
			selectedAppointmentEl.style.display = 'none';
		}
		
		// Hide booking form
		if (formEl) {
			formEl.style.display = 'none';
		}
		
		// Update visual selection
		if (staffListEl) {
			staffListEl.querySelectorAll('.mbc-staff-card').forEach(card => {
				card.classList.remove('selected');
				if (parseInt(card.dataset.staffId, 10) === selectedStaff) {
					card.classList.add('selected');
				}
			});
		}
		
		// Clear timeslot selection visual
		if (timeSlotsGrid) {
			timeSlotsGrid.querySelectorAll('.mbc-slot').forEach(slot => {
				slot.classList.remove('is-selected');
			});
		}

		// Load available timeslots for this staff and date
		loadTimeslots(selectedDate, selectedStaff, selectedService);
	}

	// Step 6: Load Timeslots
	function loadTimeslots(date, staffId, serviceId) {
		if (!timeSlotsContainer || !timeSlotsGrid) return;

		timeSlotsGrid.innerHTML = '<p>Loading available times...</p>';
		timeSlotsContainer.style.display = 'block';

		const queryParams = `date=${encodeURIComponent(date)}&staff=${staffId}&service=${serviceId}`;

		apiFetch({
			path: `/mpeti-booking-calendar/v1/available-slots?${queryParams}`,
			headers: { 'X-WP-Nonce': MBCBooking.restNonce },
		})
		.then((res) => {
			if (res && res.slots) {
				renderTimeslots(res.slots, date);
			} else {
				timeSlotsGrid.innerHTML = '<p class="mbc-no-slots">No available time slots.</p>';
			}
		})
		.catch((err) => {
			console.error('Error fetching slots:', err);
			timeSlotsGrid.innerHTML = '<p class="mbc-error">Error loading time slots.</p>';
		});
	}

	// Step 7: Render Timeslots
	function renderTimeslots(slots, date) {
		if (!timeSlotsGrid) return;

		// Format date for display
		const dateObj = new Date(date + 'T00:00:00');
		const dateHeader = timeSlotsContainer.querySelector('.mbc-selected-date');
		if (dateHeader) {
			dateHeader.textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
		}

		timeSlotsGrid.innerHTML = '';

		if (!slots || slots.length === 0) {
			timeSlotsGrid.innerHTML = '<p class="mbc-no-slots">No available time slots for this date.</p>';
			return;
		}

		slots.forEach((slot) => {
			const slotEl = document.createElement('div');
			slotEl.className = `mbc-slot ${slot.available ? 'is-available' : 'is-booked'}`;
			slotEl.textContent = formatTime(slot.time);
			slotEl.dataset.time = slot.time;
			
			if (slot.available) {
				slotEl.setAttribute('role', 'button');
				slotEl.setAttribute('tabindex', '0');
				slotEl.addEventListener('click', () => {
					selectTimeslot(slot.time);
				});
				slotEl.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						selectTimeslot(slot.time);
					}
				});
			} else {
				slotEl.style.cursor = 'not-allowed';
			}
			
			timeSlotsGrid.appendChild(slotEl);
		});
	}

	// Step 8: Select Timeslot
	function selectTimeslot(time) {
		selectedTime = time;
		
		// Update visual selection
		if (timeSlotsGrid) {
			timeSlotsGrid.querySelectorAll('.mbc-slot').forEach(el => {
				el.classList.remove('is-selected');
				if (el.dataset.time === time) {
					el.classList.add('is-selected');
				}
			});
		}

		// Show selected appointment details
		const selectedAppointmentEl = document.getElementById('mbc-selected-appointment');
		if (selectedAppointmentEl && selectedDate) {
			const dateObj = new Date(selectedDate + 'T00:00:00');
			const dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
			const timeStr = formatTime(time);
			
			const staffEl = document.getElementById('mbc-selection-staff');
			if (staffEl && selectedStaffName) {
				staffEl.textContent = selectedStaffName;
			}
			document.getElementById('mbc-selection-date').textContent = dateStr;
			document.getElementById('mbc-selection-time').textContent = timeStr;
			selectedAppointmentEl.style.display = 'block';
		}

		// Show booking form
		if (formEl) {
			document.getElementById('mbc-form-service-id').value = selectedService;
			document.getElementById('mbc-form-staff-id').value = selectedStaff;
			document.getElementById('mbc-form-date').value = selectedDate;
			document.getElementById('mbc-form-time').value = selectedTime;
			formEl.style.display = 'block';
			formEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
	}

	// Calendar Availability Check
	function checkAvailabilityForDate(date) {
		if (!selectedService) return Promise.resolve(false);
		
		// Cache key includes service ID to ensure service-specific caching
		const cacheKey = `${selectedService}_${date}`;
		if (availabilityCache[cacheKey] !== undefined) {
			return Promise.resolve(availabilityCache[cacheKey]);
		}

		return apiFetch({
			path: `/mpeti-booking-calendar/v1/available-slots?date=${date}&service=${selectedService}`,
			headers: { 'X-WP-Nonce': MBCBooking.restNonce },
		})
		.then((res) => {
			if (res && res.slots && Array.isArray(res.slots)) {
				const hasAvailable = res.slots.some(slot => slot.available === true);
				availabilityCache[cacheKey] = hasAvailable;
				return hasAvailable;
			}
			availabilityCache[cacheKey] = false;
			return false;
		})
		.catch(() => {
			availabilityCache[cacheKey] = false;
			return false;
		});
	}

	function updateCalendarAvailability() {
		if (!calendarEl || !selectedService) return;

		const grid = calendarEl.querySelector('.mbc-calendar-grid');
		if (!grid) return;

		const today = new Date();
		today.setHours(0, 0, 0, 0);

		// First, clear all availability classes from all days to ensure clean state
		grid.querySelectorAll('.mbc-calendar-day').forEach(dayEl => {
			dayEl.classList.remove('is-available-day', 'mbc-has-slots', 'no-slots-available', 'mbc-no-slots');
			dayEl.removeAttribute('role');
			dayEl.removeAttribute('tabindex');
			dayEl.removeAttribute('data-handler-attached');
		});

		const dayElements = grid.querySelectorAll('.mbc-calendar-day:not(.mbc-past):not(.mbc-other-month)');
		const datesToCheck = [];

		// Helper function to format date in local timezone (YYYY-MM-DD)
		function formatLocalDate(date) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		}

		dayElements.forEach(dayEl => {
			const dateStr = dayEl.dataset.date;
			if (dateStr) {
				// Parse date string as local date (YYYY-MM-DD)
				const [year, month, day] = dateStr.split('-').map(Number);
				const dateObj = new Date(year, month - 1, day);
				dateObj.setHours(0, 0, 0, 0);
				if (dateObj >= today) {
					datesToCheck.push(dateStr);
				}
			}
		});

		const checkPromises = datesToCheck.map(date => {
			// Capture current selectedService to ensure we use the correct service even if it changes during async operations
			const serviceToCheck = selectedService;
			
			return checkAvailabilityForDate(date).then(hasAvailable => {
				// Double-check that the service hasn't changed while we were checking
				if (serviceToCheck !== selectedService) {
					return; // Skip updating if service changed
				}
				
				const dayEl = grid.querySelector(`[data-date="${date}"]`);
				if (dayEl) {
					// Remove any inline cursor styles
					dayEl.style.cursor = '';
					
					if (hasAvailable) {
						dayEl.classList.add('is-available-day', 'mbc-has-slots');
						dayEl.classList.remove('no-slots-available', 'mbc-no-slots');
						dayEl.setAttribute('role', 'button');
						dayEl.setAttribute('tabindex', '0');
						if (!dayEl.hasAttribute('data-handler-attached')) {
							dayEl.addEventListener('click', () => handleDateClick(date));
							dayEl.addEventListener('keydown', (e) => {
								if (e.key === 'Enter' || e.key === ' ') {
									e.preventDefault();
									handleDateClick(date);
								}
							});
							dayEl.setAttribute('data-handler-attached', 'true');
						}
					} else {
						dayEl.classList.remove('is-available-day', 'mbc-has-slots');
						dayEl.classList.add('no-slots-available', 'mbc-no-slots');
						dayEl.removeAttribute('role');
						dayEl.removeAttribute('tabindex');
						dayEl.removeAttribute('data-handler-attached');
					}
				}
			});
		});

		Promise.all(checkPromises).catch(() => {});
	}

	function updateCalendarMonth() {
		if (!calendarEl) return;
		
		const firstDay = new Date(currentYear, currentMonth, 1);
		const lastDay = new Date(currentYear, currentMonth + 1, 0);
		const startDate = new Date(firstDay);
		startDate.setDate(startDate.getDate() - (startDate.getDay() === 0 ? 6 : startDate.getDay() - 1));
		const endDate = new Date(lastDay);
		endDate.setDate(endDate.getDate() + (7 - (endDate.getDay() === 0 ? 7 : endDate.getDay())));

		const monthName = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
		const monthEl = calendarEl.querySelector('.mbc-current-month');
		if (monthEl) {
			monthEl.textContent = monthName;
		}

		const grid = calendarEl.querySelector('.mbc-calendar-grid');
		if (!grid) return;

		// Clear all availability classes and handlers from existing days before clearing
		grid.querySelectorAll('.mbc-calendar-day').forEach(dayEl => {
			dayEl.classList.remove('is-available-day', 'mbc-has-slots', 'no-slots-available', 'mbc-no-slots');
			dayEl.removeAttribute('role');
			dayEl.removeAttribute('tabindex');
			dayEl.removeAttribute('data-handler-attached');
		});

		grid.innerHTML = '';
		const today = new Date();
		today.setHours(0, 0, 0, 0);

		// Helper function to format date in local timezone (YYYY-MM-DD)
		function formatLocalDate(date) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		}

		const current = new Date(startDate);
		while (current <= endDate) {
			const dateStr = formatLocalDate(current);
			const isCurrentMonth = current.getMonth() === currentMonth;
			const isToday = dateStr === formatLocalDate(today);
			const isPast = current < today;

			const dayEl = document.createElement('div');
			dayEl.className = 'mbc-calendar-day';
			if (!isCurrentMonth) dayEl.classList.add('mbc-other-month');
			if (isToday) dayEl.classList.add('mbc-today');
			if (isPast) dayEl.classList.add('mbc-past');
			
			dayEl.dataset.date = dateStr;
			
			const dayContent = document.createElement('div');
			dayContent.className = 'mbc-day-content';
			dayContent.textContent = current.getDate();
			dayEl.appendChild(dayContent);
			
			const indicator = document.createElement('span');
			indicator.className = 'mbc-availability-dot';
			indicator.setAttribute('aria-hidden', 'true');
			dayEl.appendChild(indicator);

			// Set initial cursor state based on day type
			if (isPast || !isCurrentMonth) {
				dayEl.style.cursor = 'default';
			} else if (!selectedService) {
				dayEl.style.cursor = 'not-allowed';
			}

			grid.appendChild(dayEl);
			current.setDate(current.getDate() + 1);
		}

		// Clear availability cache when month changes to prevent stale data
		// Only clear cache entries for the current selected service
		if (selectedService) {
			Object.keys(availabilityCache).forEach(key => {
				if (key.startsWith(`${selectedService}_`)) {
					delete availabilityCache[key];
				}
			});
		} else {
			// If no service selected, clear all cache
			Object.keys(availabilityCache).forEach(key => delete availabilityCache[key]);
		}

		if (selectedService) {
			setTimeout(() => {
				updateCalendarAvailability();
			}, 100);
		} else {
			// If no service selected, mark all future days as not available
			const grid = calendarEl.querySelector('.mbc-calendar-grid');
			if (grid) {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				grid.querySelectorAll('.mbc-calendar-day:not(.mbc-past):not(.mbc-other-month)').forEach(dayEl => {
					const dateStr = dayEl.dataset.date;
					if (dateStr) {
						// Parse date string as local date (YYYY-MM-DD)
						const [year, month, day] = dateStr.split('-').map(Number);
						const dateObj = new Date(year, month - 1, day);
						dateObj.setHours(0, 0, 0, 0);
						if (dateObj >= today) {
							dayEl.classList.add('no-slots-available', 'mbc-no-slots');
							dayEl.classList.remove('is-available-day', 'mbc-has-slots');
							dayEl.style.cursor = 'not-allowed';
						}
					}
				});
			}
		}
	}

	function formatTime(timeStr) {
		const [hours, minutes] = timeStr.split(':');
		const hour = parseInt(hours, 10);
		const ampm = hour >= 12 ? 'PM' : 'AM';
		const displayHour = hour % 12 || 12;
		// Use non-breaking space to keep time and AM/PM on same line
		return `${displayHour}:${minutes}\u00A0${ampm}`;
	}

	function resetSelections() {
		selectedDate = null;
		selectedStaff = null;
		selectedStaffName = null;
		selectedTime = null;
		
		if (staffListEl) {
			staffListEl.innerHTML = '';
			document.querySelector('.mbc-step-staff').style.display = 'none';
		}
		
		if (timeSlotsContainer) {
			timeSlotsContainer.style.display = 'none';
		}
		
		const selectedAppointmentEl = document.getElementById('mbc-selected-appointment');
		if (selectedAppointmentEl) {
			selectedAppointmentEl.style.display = 'none';
		}
		
		if (formEl) {
			formEl.style.display = 'none';
		}
	}

	function message(text, type = 'info') {
		if (!messageEl) return;
		messageEl.style.display = text ? 'block' : 'none';
		messageEl.textContent = text;
		messageEl.className = `mbc-form-message ${type}`;
	}

	// Form Submission
	if (formEl) {
		formEl.addEventListener('submit', function(e) {
			e.preventDefault();
			
			const date = document.getElementById('mbc-form-date').value;
			const time = document.getElementById('mbc-form-time').value;
			
			if (!date || !time) {
				message('Please select a date and time', 'error');
				return;
			}

			const data = new FormData(formEl);
			// Append nonce to FormData
			if (MBCBooking.nonce) {
				data.append('nonce', MBCBooking.nonce);
			}

			// Log for debugging
			console.log('Submitting booking with nonce:', MBCBooking.nonce ? 'present' : 'missing');
			console.log('Form data:', {
				name: data.get('name'),
				email: data.get('email'),
				date: data.get('date'),
				time: data.get('time'),
				service_id: data.get('service_id'),
				staff_id: data.get('staff_id')
			});

			apiFetch({
				path: '/mpeti-booking-calendar/v1/book',
				method: 'POST',
				headers: { 
					'X-WP-Nonce': MBCBooking.restNonce,
				},
				body: data,
			})
			.then((res) => {
				if (res && res.success === true) {
					showBookingSuccess();
				} else {
					const errorMsg = (res && res.message) || MBCBooking.i18n.error || 'There was an error. Please try again.';
					message(errorMsg, 'error');
				}
			})
			.catch((err) => {
				console.error('Booking error:', err);
				const errorMsg = (err && err.message) || MBCBooking.i18n.error || 'There was an error. Please try again.';
				message(errorMsg, 'error');
			});
		});
	}

	// Show booking success message
	function showBookingSuccess() {
		console.log('showBookingSuccess called');
		
		// Ensure booking form container is visible FIRST
		const bookingFormContainer = document.getElementById('mbc-booking-form');
		console.log('Booking form container:', bookingFormContainer);
		if (bookingFormContainer) {
			bookingFormContainer.style.display = 'block';
			bookingFormContainer.style.visibility = 'visible';
		}
		
		// Hide all form elements
		if (formEl) {
			formEl.style.display = 'none';
		}
		
		// Hide appointment details
		const selectedAppointmentEl = document.getElementById('mbc-selected-appointment');
		if (selectedAppointmentEl) {
			selectedAppointmentEl.style.display = 'none';
		}
		
		// Hide timeslots
		if (timeSlotsContainer) {
			timeSlotsContainer.style.display = 'none';
		}
		
		// Hide staff list
		const staffStep = document.querySelector('.mbc-step-staff');
		if (staffStep) {
			staffStep.style.display = 'none';
		}
		
		// Hide calendar
		if (calendarEl) {
			calendarEl.style.display = 'none';
		}
		
		// Hide service selection container
		const serviceStep = document.querySelector('.mbc-step-service');
		if (serviceStep) {
			serviceStep.style.display = 'none';
		}
		
		// Hide service grid (backup)
		if (serviceGrid) {
			serviceGrid.style.display = 'none';
		}
		
		// Show success message - try to find or create it
		let successEl = document.getElementById('mbc-booking-success');
		console.log('Success element found:', successEl);
		
		// If not found, try to create it dynamically
		if (!successEl && bookingFormContainer) {
			console.log('Creating success element dynamically');
			successEl = document.createElement('div');
			successEl.id = 'mbc-booking-success';
			successEl.className = 'mbc-booking-success show';
			successEl.innerHTML = `
				<div class="mbc-success-icon">✓</div>
				<h3>${MBCBooking.i18n.thankYou || 'Thank You!'}</h3>
				<p class="mbc-success-message">
					${MBCBooking.i18n.successMessage || 'Your appointment request has been submitted successfully.'}
				</p>
				<p class="mbc-success-details">
					${MBCBooking.i18n.successDetails || 'We will review your request and send you a confirmation email shortly.'}
				</p>
				<button type="button" class="button button-primary mbc-new-appointment-btn" id="mbc-new-appointment-btn">
					${MBCBooking.i18n.newAppointment || 'Book Another Appointment'}
				</button>
			`;
			bookingFormContainer.appendChild(successEl);
		}
		
		if (successEl) {
			// Show immediately
			successEl.style.display = 'block';
			successEl.style.visibility = 'visible';
			successEl.style.opacity = '1';
			successEl.removeAttribute('hidden');
			successEl.setAttribute('aria-hidden', 'false');
			successEl.classList.add('show');
			
			// Also try with a delay to ensure it's visible
			setTimeout(() => {
				successEl.style.display = 'block';
				successEl.style.visibility = 'visible';
				successEl.style.opacity = '1';
				successEl.classList.add('show');
				successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				
				// Ensure button handler is attached
				const newAppointmentBtn = document.getElementById('mbc-new-appointment-btn');
				console.log('New appointment button:', newAppointmentBtn);
				if (newAppointmentBtn) {
					// Remove any existing listeners to avoid duplicates
					const newBtn = newAppointmentBtn.cloneNode(true);
					newAppointmentBtn.parentNode.replaceChild(newBtn, newAppointmentBtn);
					newBtn.addEventListener('click', startNewAppointment);
				}
			}, 100);
		} else {
			console.error('Could not find or create success element!');
		}
		
		// Reset form
		if (formEl) {
			formEl.reset();
		}
	}

	// Start new appointment
	function startNewAppointment() {
		// Hide success message
		const successEl = document.getElementById('mbc-booking-success');
		if (successEl) {
			successEl.style.display = 'none';
			successEl.classList.remove('show');
		}
		
		// Reset all selections
		resetSelections();
		selectedService = null;
		
		// Clear service selection
		if (serviceSelect) {
			serviceSelect.value = '';
		}
		if (serviceGrid) {
			serviceGrid.querySelectorAll('.mbc-service-card').forEach(card => {
				card.classList.remove('selected');
			});
			serviceGrid.style.display = 'grid';
		}
		
		// Reset calendar
		if (calendarEl) {
			calendarEl.classList.add('mbc-disabled');
			calendarEl.style.display = 'block';
			// Clear selected date
			calendarEl.querySelectorAll('.mbc-calendar-day').forEach(day => {
				day.classList.remove('is-selected');
			});
		}
		
		// Clear availability cache
		availabilityCache = {};
		
		// Scroll to top
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	// Initialize
	document.addEventListener('DOMContentLoaded', () => {
		try {
			initServiceSelection();

			// New Appointment button handler
			const newAppointmentBtn = document.getElementById('mbc-new-appointment-btn');
			if (newAppointmentBtn) {
				newAppointmentBtn.addEventListener('click', startNewAppointment);
			}

			// Month navigation
			if (calendarEl) {
				const prevBtn = calendarEl.querySelector('.mbc-prev-month');
				const nextBtn = calendarEl.querySelector('.mbc-next-month');
				
				if (prevBtn) {
					prevBtn.addEventListener('click', () => {
						currentMonth--;
						if (currentMonth < 0) {
							currentMonth = 11;
							currentYear--;
						}
						updateCalendarMonth();
					});
				}
				
				if (nextBtn) {
					nextBtn.addEventListener('click', () => {
						currentMonth++;
						if (currentMonth > 11) {
							currentMonth = 0;
							currentYear++;
						}
						updateCalendarMonth();
					});
				}

				// Initialize calendar - always render it
				const existingGrid = calendarEl.querySelector('.mbc-calendar-month .mbc-calendar-grid');
				if (existingGrid && existingGrid.children.length > 0) {
					// Calendar already rendered by PHP, just update availability if service is selected
					if (selectedService) {
						setTimeout(() => {
							updateCalendarAvailability();
						}, 200);
					}
				} else {
					// Calendar not rendered, generate it with JS
					updateCalendarMonth();
				}
				
				if (!selectedService) {
					calendarEl.classList.add('mbc-disabled');
				} else {
					calendarEl.classList.remove('mbc-disabled');
				}
			}
		} catch (error) {
			console.error('mpeti Booking Calendar initialization error:', error);
		}
	});
})(window.wp, window.MBCBooking || {});
