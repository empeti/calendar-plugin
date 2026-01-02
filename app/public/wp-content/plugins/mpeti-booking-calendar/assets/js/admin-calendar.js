(function( wp, MBCAdmin ) {
	const { createElement: el, useState, useEffect, useMemo } = wp.element;
	const { render } = wp.element;
	const { SelectControl, Spinner } = wp.components;
	const apiFetch = wp.apiFetch;

	function formatDate(dateStr) {
		if (!dateStr) return '';
		const date = new Date(dateStr + 'T00:00:00');
		return date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
	}

	function formatTime(timeStr) {
		if (!timeStr) return '';
		const [hours, minutes] = timeStr.split(':');
		const hour = parseInt(hours, 10);
		const ampm = hour >= 12 ? 'PM' : 'AM';
		const displayHour = hour % 12 || 12;
		return `${displayHour}:${minutes} ${ampm}`;
	}

	function AppointmentsList() {
		const [appointments, setAppointments] = useState([]);
		const [loading, setLoading] = useState(false);
		const [filters, setFilters] = useState({ staff: [], service: [], status: [] });
		const [selectedAppointment, setSelectedAppointment] = useState(null);
		const [updatingStatus, setUpdatingStatus] = useState(false);
		const [staffOptions, setStaffOptions] = useState([]);
		const [serviceOptions, setServiceOptions] = useState([]);
		const [expandedStaffGroups, setExpandedStaffGroups] = useState({});
		const [searchFilters, setSearchFilters] = useState({ name: '', email: '', date: '' });
		const [activeSearchFilters, setActiveSearchFilters] = useState({ name: '', email: '', date: '' });
		const [searchExpanded, setSearchExpanded] = useState(false);
		const [filtersExpanded, setFiltersExpanded] = useState(false);
		const [viewMode, setViewMode] = useState('calendar'); // 'list', 'calendar', or 'day'
		const [expandedDays, setExpandedDays] = useState({}); // Track which days are expanded on mobile
		const [selectedDay, setSelectedDay] = useState(() => {
			// Default to today
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			return today;
		});
		const [currentWeekStart, setCurrentWeekStart] = useState(() => {
			// Get Monday of current week
			const today = new Date();
			const day = today.getDay();
			const diff = today.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
			const monday = new Date(today.setDate(diff));
			monday.setHours(0, 0, 0, 0);
			return monday;
		});

		useEffect(() => {
			loadStaffAndServices();
		}, []);

		useEffect(() => {
			loadAppointments();
		}, [filters]);

		function loadStaffAndServices() {
			// Load staff
			apiFetch({
				path: '/mpeti-booking-calendar/v1/staff',
				headers: { 'X-WP-Nonce': MBCAdmin.nonce },
			})
				.then((response) => {
					if (response && Array.isArray(response)) {
						const options = [];
						response.forEach((staff) => {
							options.push({ label: staff.name, value: String(staff.id) });
						});
						setStaffOptions(options);
					}
				})
				.catch((error) => {
					console.error('Error loading staff:', error);
				});

			// Load services
			apiFetch({
				path: '/mpeti-booking-calendar/v1/services',
				headers: { 'X-WP-Nonce': MBCAdmin.nonce },
			})
				.then((response) => {
					if (response && Array.isArray(response)) {
						const options = [];
						response.forEach((service) => {
							options.push({ label: service.name, value: String(service.id) });
						});
						setServiceOptions(options);
					}
				})
				.catch((error) => {
					console.error('Error loading services:', error);
				});
		}

		function loadAppointments() {
			setLoading(true);
			const params = new URLSearchParams();
			if (filters.staff && filters.staff.length > 0) {
				filters.staff.forEach(id => params.append('staff', id));
			}
			if (filters.service && filters.service.length > 0) {
				filters.service.forEach(id => params.append('service', id));
			}
			if (filters.status && filters.status.length > 0) {
				filters.status.forEach(status => params.append('status', status));
			}
			apiFetch({
				path: `/mpeti-booking-calendar/v1/appointments?${params.toString()}`,
				headers: { 'X-WP-Nonce': MBCAdmin.nonce },
			})
				.then((response) => {
					if (response && Array.isArray(response)) {
						// Filter to only upcoming appointments and sort by date/time
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						
						const upcoming = response.filter((apt) => {
							if (!apt.date) return false;
							const aptDate = new Date(apt.date + 'T00:00:00');
							return aptDate >= today;
						}).sort((a, b) => {
							// Sort by date first, then time
							const dateCompare = a.date.localeCompare(b.date);
							if (dateCompare !== 0) return dateCompare;
							return (a.time || '').localeCompare(b.time || '');
						});
						
						setAppointments(upcoming);
					} else {
						console.error('Invalid appointments response:', response);
						setAppointments([]);
					}
				})
				.catch((error) => {
					console.error('Error loading appointments:', error);
					setAppointments([]);
				})
				.finally(() => setLoading(false));
		}

		// Update appointment status
		function updateAppointmentStatus(appointmentId, newStatus) {
			setUpdatingStatus(true);
			apiFetch({
				path: `/mpeti-booking-calendar/v1/appointments/${appointmentId}/status`,
				method: 'POST',
				headers: { 'X-WP-Nonce': MBCAdmin.nonce },
				data: { status: newStatus }
			})
				.then((response) => {
					if (response && response.success) {
						// Reload appointments to reflect the change
						loadAppointments();
						setSelectedAppointment(null);
					} else {
						alert('Failed to update appointment status');
					}
				})
				.catch((error) => {
					console.error('Error updating appointment status:', error);
					alert('Error updating appointment status');
				})
				.finally(() => setUpdatingStatus(false));
		}

		// Render appointment modal
		function renderAppointmentModal() {
			if (!selectedAppointment) return null;

			try {
				const apt = selectedAppointment;
				const status = apt.status || 'pending';
				const duration = apt.service_duration || 60;
				const endTime = calculateEndTime(apt.time || '08:00', duration);
			const serviceName = apt.service_name || (apt.service ? 'Service #' + apt.service : 'No Service');
			const defaultAvatar = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#e0e0e0"/><circle cx="32" cy="24" r="12" fill="#999"/><path d="M32 40c-8 0-16 4-16 8v4h32v-4c0-4-8-8-16-8z" fill="#999"/></svg>');
			const staffPhoto = apt.staff_photo || defaultAvatar;

			return el(
				'div',
				{ 
					className: 'mbc-appointment-modal-overlay',
					onClick: () => !updatingStatus && setSelectedAppointment(null)
				},
				el('div', 
					{ 
						className: 'mbc-appointment-modal',
						onClick: (e) => e.stopPropagation()
					},
					el('div', { className: 'mbc-appointment-modal-header' },
						el('h2', null, 'Appointment Details'),
						el('button', {
							className: 'mbc-appointment-modal-close',
							onClick: () => !updatingStatus && setSelectedAppointment(null),
							disabled: updatingStatus
						}, '×')
					),
					el('div', { className: 'mbc-appointment-modal-content' },
						el('div', { className: 'mbc-appointment-modal-avatar' },
							el('img', { 
								src: staffPhoto,
								alt: apt.staff_name || 'Staff'
							})
						),
						el('div', { className: 'mbc-appointment-modal-details' },
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Customer:'),
								el('span', { className: 'mbc-appointment-modal-value' }, apt.customer || 'N/A')
							),
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Email:'),
								el('span', { className: 'mbc-appointment-modal-value' }, apt.customer_email || 'N/A')
							),
							apt.customer_phone ? el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Phone:'),
								el('span', { className: 'mbc-appointment-modal-value' }, apt.customer_phone)
							) : null,
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Date:'),
								el('span', { className: 'mbc-appointment-modal-value' }, formatDate(apt.date))
							),
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Time:'),
								el('span', { className: 'mbc-appointment-modal-value' }, formatTime(apt.time) + ' - ' + formatTime(endTime))
							),
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Service:'),
								el('span', { className: 'mbc-appointment-modal-value' }, serviceName)
							),
							apt.staff_name ? el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Staff:'),
								el('span', { className: 'mbc-appointment-modal-value' }, apt.staff_name)
							) : null,
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Status:'),
								el('span', { className: `mbc-appointment-modal-value mbc-status-${status}` }, status)
							),
							el('div', { className: 'mbc-appointment-modal-field' },
								el('strong', null, 'Notes:'),
								el('div', { className: 'mbc-appointment-modal-notes' }, apt.notes || 'No notes')
							)
						)
					),
					el('div', { className: 'mbc-appointment-modal-actions' },
						el('a', {
							href: `${window.location.origin}/wp-admin/post.php?post=${apt.id}&action=edit`,
							className: 'mbc-appointment-modal-btn mbc-appointment-modal-edit',
							target: '_self'
						}, 'Edit Appointment'),
						status !== 'confirmed' ? el('button', {
							className: 'mbc-appointment-modal-btn mbc-appointment-modal-confirm',
							onClick: () => updateAppointmentStatus(apt.id, 'confirmed'),
							disabled: updatingStatus
						}, updatingStatus ? 'Updating...' : 'Confirm Appointment') : null,
						status !== 'cancelled' ? el('button', {
							className: 'mbc-appointment-modal-btn mbc-appointment-modal-cancel',
							onClick: () => updateAppointmentStatus(apt.id, 'cancelled'),
							disabled: updatingStatus
						}, updatingStatus ? 'Updating...' : 'Cancel Appointment') : null,
						status === 'cancelled' ? el('button', {
							className: 'mbc-appointment-modal-btn mbc-appointment-modal-confirm',
							onClick: () => updateAppointmentStatus(apt.id, 'pending'),
							disabled: updatingStatus
						}, updatingStatus ? 'Updating...' : 'Reactivate Appointment') : null
					)
				)
			);
			} catch (error) {
				console.error('Error rendering appointment modal:', error);
				return null;
			}
		}

		// Filter appointments based on search filters
		function filterAppointmentsBySearch(apts) {
			const nameFilter = (activeSearchFilters.name || '').trim().toLowerCase();
			const emailFilter = (activeSearchFilters.email || '').trim().toLowerCase();
			const dateFilter = (activeSearchFilters.date || '').trim();
			
			// If no filters are set, return all
			if (!nameFilter && !emailFilter && !dateFilter) {
				return apts;
			}
			
			return apts.filter((apt) => {
				// Filter by name
				if (nameFilter) {
					const name = (apt.customer || '').toLowerCase();
					if (!name.includes(nameFilter)) return false;
				}
				
				// Filter by email
				if (emailFilter) {
					const email = (apt.customer_email || '').toLowerCase();
					if (!email.includes(emailFilter)) return false;
				}
				
				// Filter by date
				if (dateFilter) {
					const date = apt.date || '';
					// Check exact match or partial match
					if (!date.includes(dateFilter)) {
						// Also check formatted date
						try {
							const dateObj = new Date(date + 'T00:00:00');
							const formattedDate = dateObj.toLocaleDateString('en-US', { 
								weekday: 'long', 
								year: 'numeric', 
								month: 'long', 
								day: 'numeric' 
							}).toLowerCase();
							if (!formattedDate.includes(dateFilter.toLowerCase())) {
								return false;
							}
						} catch (e) {
							return false;
						}
					}
				}
				
				return true;
			});
		}

		// Group appointments by staff member, then by date
		function groupAppointments() {
			// First filter by search term
			const filteredAppointments = filterAppointmentsBySearch(appointments);
			
			const grouped = {};
			
			filteredAppointments.forEach((apt) => {
				const staffName = apt.staff_name || 'Unassigned';
				const date = apt.date || 'Unknown';
				
				if (!grouped[staffName]) {
					grouped[staffName] = {};
				}
				
				if (!grouped[staffName][date]) {
					grouped[staffName][date] = [];
				}
				
				grouped[staffName][date].push(apt);
			});
			
			// Sort staff names alphabetically
			const sortedStaff = Object.keys(grouped).sort();
			
			return sortedStaff.map((staffName) => ({
				staffName,
				staffColor: grouped[staffName][Object.keys(grouped[staffName])[0]]?.[0]?.staff_color || '#4caf50',
				dates: Object.keys(grouped[staffName])
					.sort()
					.map((date) => ({
						date,
						appointments: grouped[staffName][date].sort((a, b) => 
							(a.time || '').localeCompare(b.time || '')
						),
					})),
			}));
		}

		function confirmAppointment(appointmentId) {
			apiFetch({
				path: `/mpeti-booking-calendar/v1/appointments/${appointmentId}/status`,
				method: 'POST',
				headers: { 
					'X-WP-Nonce': MBCAdmin.nonce,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({ status: 'confirmed' }),
			})
			.then((response) => {
				if (response && response.success) {
					// Reload appointments to reflect the change
					loadAppointments();
				} else {
					alert(wp.i18n.__('Failed to confirm appointment.', 'mpeti-booking-calendar'));
				}
			})
			.catch((error) => {
				console.error('Error confirming appointment:', error);
				alert(wp.i18n.__('Failed to confirm appointment.', 'mpeti-booking-calendar'));
			});
		}

		function renderAppointment(apt) {
			const isPending = (apt.status || 'pending') === 'pending';
			return el(
				'div',
				{ key: apt.id, className: 'mbc-admin-appointment-item' },
				el('div', { className: 'mbc-admin-appointment-time-status' },
					el('span', { className: 'mbc-admin-appointment-time' }, formatTime(apt.time)),
					el('span', { className: 'mbc-admin-appointment-status' }, 
						el('span', { className: `mbc-status-${apt.status}` }, apt.status || 'pending')
					)
				),
				el('div', { className: 'mbc-admin-appointment-details' },
					el('div', { className: 'mbc-admin-appointment-main' },
						el('span', { className: 'mbc-admin-appointment-customer' }, apt.customer || 'N/A'),
						apt.service_name ? el('span', { className: 'mbc-admin-appointment-service' }, apt.service_name) : null,
						apt.customer_email ? el('span', { className: 'mbc-admin-appointment-email' }, apt.customer_email) : null,
						apt.customer_phone ? el('span', { className: 'mbc-admin-appointment-phone' }, apt.customer_phone) : null,
						el('a', 
							{ 
								href: `${window.location.origin}/wp-admin/post.php?post=${apt.id}&action=edit`,
								className: 'mbc-admin-appointment-edit'
							}, 
							el('span', { className: 'mbc-edit-icon' }, '✎'),
							' ',
							wp.i18n.__('Edit', 'mpeti-booking-calendar')
						),
						isPending ? el(
							'button',
							{
								type: 'button',
								className: 'mbc-admin-appointment-confirm',
								onClick: () => confirmAppointment(apt.id),
							},
							wp.i18n.__('Confirm', 'mpeti-booking-calendar')
						) : null
					)
				)
			);
		}

		function toggleStaffGroup(staffName) {
			setExpandedStaffGroups(prev => ({
				...prev,
				[staffName]: prev[staffName] === undefined ? false : !prev[staffName]
			}));
		}

		function renderStaffGroup(group) {
			const isExpanded = expandedStaffGroups[group.staffName] !== false; // Default to expanded (undefined = true)
			return el(
				'div',
				{ key: group.staffName, className: 'mbc-admin-staff-group' },
				el('h2', 
					{ 
						className: 'mbc-admin-staff-header mbc-admin-staff-header-clickable',
						style: { borderLeftColor: group.staffColor },
						onClick: () => toggleStaffGroup(group.staffName),
					}, 
					el('span', { className: 'mbc-staff-group-toggle' }, isExpanded ? '▼' : '▶'),
					' ',
					el('span', { className: 'mbc-staff-group-name' }, group.staffName)
				),
				isExpanded ? group.dates.map((dateGroup) =>
					el(
						'div',
						{ key: dateGroup.date, className: 'mbc-admin-date-group' },
						el('h3', { className: 'mbc-admin-date-header' }, formatDate(dateGroup.date)),
						el('div', { className: 'mbc-admin-appointments-list' },
							dateGroup.appointments.map(renderAppointment)
						)
					)
				) : null
			);
		}

		function toggleFilter(filterType, value) {
			setFilters(prev => {
				const current = prev[filterType] || [];
				const index = current.indexOf(value);
				if (index > -1) {
					// Remove if already selected
					return { ...prev, [filterType]: current.filter(v => v !== value) };
				} else {
					// Add if not selected
					return { ...prev, [filterType]: [...current, value] };
				}
			});
		}

		function resetFilter(filterType) {
			setFilters(prev => ({
				...prev,
				[filterType]: []
			}));
		}

		function renderFilterGroup(label, filterType, options) {
			const selectedValues = filters[filterType] || [];
			const isAllSelected = selectedValues.length === 0;
			return el(
				'div',
				{ key: filterType, className: 'mbc-filter-group' },
				el('label', { className: 'mbc-filter-label' }, label),
				el('div', { className: 'mbc-filter-buttons' },
					el(
						'button',
						{
							key: 'all',
							type: 'button',
							className: `mbc-filter-button mbc-filter-all ${isAllSelected ? 'is-selected' : ''}`,
							onClick: () => resetFilter(filterType),
						},
						'All'
					),
					options.map(option => {
						const isSelected = selectedValues.includes(option.value);
						return el(
							'button',
							{
								key: option.value,
								type: 'button',
								className: `mbc-filter-button ${isSelected ? 'is-selected' : ''}`,
								onClick: () => toggleFilter(filterType, option.value),
							},
							option.label
						);
					})
				)
			);
		}

		// Get week dates
		function getWeekDates(startDate) {
			const dates = [];
			for (let i = 0; i < 7; i++) {
				const date = new Date(startDate);
				date.setDate(startDate.getDate() + i);
				dates.push(date);
			}
			return dates;
		}

		// Filter appointments by button filters (Status, Staff, Service)
		function filterAppointmentsByButtons(apts) {
			let filtered = apts;
			
			// Filter by status
			if (filters.status && filters.status.length > 0) {
				filtered = filtered.filter(apt => filters.status.includes(apt.status || 'pending'));
			}
			
			// Filter by staff
			if (filters.staff && filters.staff.length > 0) {
				filtered = filtered.filter(apt => {
					const staffId = String(apt.staff || apt.staff_id || '');
					return filters.staff.includes(staffId);
				});
			}
			
			// Filter by service
			if (filters.service && filters.service.length > 0) {
				filtered = filtered.filter(apt => {
					const serviceId = String(apt.service || apt.service_id || '');
					return filters.service.includes(serviceId);
				});
			}
			
			return filtered;
		}

		// Format date as YYYY-MM-DD (local timezone)
		function formatDateLocal(date) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		}

		// Get appointments for a specific date
		function getAppointmentsForDate(date) {
			const dateStr = formatDateLocal(date);
			// First filter by button filters
			const buttonFiltered = filterAppointmentsByButtons(appointments);
			// Then filter by search
			const searchFiltered = filterAppointmentsBySearch(buttonFiltered);
			// Finally filter by date
			return searchFiltered.filter(apt => apt.date === dateStr);
		}

		// Navigate weeks
		function navigateWeek(direction) {
			setCurrentWeekStart(prev => {
				const newDate = new Date(prev);
				newDate.setDate(prev.getDate() + (direction * 7));
				return newDate;
			});
		}

		// Format week range
		function formatWeekRange(startDate) {
			const endDate = new Date(startDate);
			endDate.setDate(startDate.getDate() + 6);
			const startStr = startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
			const endStr = endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
			return `${startStr} - ${endStr}`;
		}

		// Calculate end time from start time and duration
		function calculateEndTime(startTime, durationMinutes) {
			const [hours, minutes] = startTime.split(':').map(Number);
			const startDate = new Date();
			startDate.setHours(hours, minutes, 0, 0);
			const endDate = new Date(startDate.getTime() + durationMinutes * 60 * 1000);
			const endHours = endDate.getHours();
			const endMinutes = endDate.getMinutes();
			return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`;
		}

		// Render appointment for calendar view
		function renderCalendarAppointment(apt, style) {
			const isPending = (apt.status || 'pending') === 'pending';
			const duration = apt.service_duration || 60;
			const endTime = calculateEndTime(apt.time || '08:00', duration);
			const serviceName = apt.service_name || (apt.service ? 'Service #' + apt.service : 'No Service');
			
			// Default avatar SVG if no photo
			const defaultAvatar = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#e0e0e0"/><circle cx="16" cy="12" r="6" fill="#999"/><path d="M16 20c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z" fill="#999"/></svg>');
			const staffPhoto = apt.staff_photo || defaultAvatar;
			
			const status = apt.status || 'pending';
			return el(
				'div',
				{ 
					key: apt.id, 
					className: `mbc-calendar-appointment-item mbc-status-${status}`,
					style: style,
					onClick: () => setSelectedAppointment(apt)
				},
				el('div', { className: 'mbc-calendar-appointment-content' },
					el('div', { className: 'mbc-calendar-appointment-avatar' },
						el('img', { 
							className: 'mbc-calendar-appointment-staff-icon',
							src: staffPhoto,
							alt: apt.staff_name || 'Staff'
						})
					),
					el('div', { className: 'mbc-calendar-appointment-right' },
						el('div', { className: 'mbc-calendar-appointment-time' }, 
							formatTime(apt.time) + ' - ' + formatTime(endTime)
						),
						el('div', { className: 'mbc-calendar-appointment-details' },
							el('div', { className: 'mbc-calendar-appointment-customer' }, apt.customer || 'N/A'),
							el('div', { className: 'mbc-calendar-appointment-service' }, serviceName),
							(apt.staff_name && apt.staff_name.trim()) ? el('div', { className: 'mbc-calendar-appointment-staff' },
								el('span', { className: 'mbc-calendar-appointment-staff-name' }, apt.staff_name)
							) : null
						),
						el('div', { className: `mbc-calendar-appointment-status mbc-status-${apt.status}` }, apt.status || 'pending')
					)
				)
			);
		}

		// Generate time slots (half-hour intervals from 8:00 to 20:00)
		function generateTimeSlots() {
			const slots = [];
			for (let hour = 8; hour < 20; hour++) {
				slots.push(`${String(hour).padStart(2, '0')}:00`);
				slots.push(`${String(hour).padStart(2, '0')}:30`);
			}
			return slots;
		}

		// Check if two appointments overlap
		function appointmentsOverlap(apt1, apt2) {
			const timeToMinutes = (timeStr) => {
				const [hour, min] = (timeStr || '08:00').split(':').map(Number);
				return hour * 60 + min;
			};
			
			const start1 = timeToMinutes(apt1.time);
			const end1 = start1 + (apt1.service_duration || 60);
			const start2 = timeToMinutes(apt2.time);
			const end2 = start2 + (apt2.service_duration || 60);
			
			// Check if they overlap (one starts before the other ends)
			return (start1 < end2 && start2 < end1);
		}

		// Group overlapping appointments
		function groupOverlappingAppointments(appointments) {
			const groups = [];
			const processed = new Set();
			
			appointments.forEach((apt, index) => {
				if (processed.has(index)) return;
				
				const group = [apt];
				processed.add(index);
				
				// Find all appointments that overlap with any appointment in this group
				let foundNew = true;
				while (foundNew) {
					foundNew = false;
					appointments.forEach((otherApt, otherIndex) => {
						if (processed.has(otherIndex)) return;
						
						// Check if this appointment overlaps with any in the current group
						const overlaps = group.some(groupApt => appointmentsOverlap(groupApt, otherApt));
						if (overlaps) {
							group.push(otherApt);
							processed.add(otherIndex);
							foundNew = true;
						}
					});
				}
				
				groups.push(group);
			});
			
			return groups;
		}

		// Calculate appointment container position (for overlapping groups)
		function getAppointmentContainerStyle(group) {
			// Use the earliest start time in the group
			const earliestApt = group.reduce((earliest, apt) => {
				const currentTime = (apt.time || '08:00').split(':').map(Number);
				const earliestTime = (earliest.time || '08:00').split(':').map(Number);
				if (currentTime[0] < earliestTime[0] || (currentTime[0] === earliestTime[0] && currentTime[1] < earliestTime[1])) {
					return apt;
				}
				return earliest;
			}, group[0]);
			
			const [startHour, startMin] = (earliestApt.time || '08:00').split(':').map(Number);
			const earliestStartMinutes = startHour * 60 + startMin;
			
			// Responsive slot height based on screen size
			const isMobile = window.innerWidth <= 768;
			const isSmallMobile = window.innerWidth <= 480;
			const SLOT_HEIGHT = isSmallMobile ? 75 : (isMobile ? 80 : 90);
			const PIXELS_PER_MINUTE = SLOT_HEIGHT / 30;
			
			// Calculate position from top of time grid (8:00 AM = 0px)
			const minutesFrom8AM = earliestStartMinutes - (8 * 60);
			const top = minutesFrom8AM * PIXELS_PER_MINUTE;
			
			return {
				style: {
					position: 'absolute',
					top: `${Math.round(top)}px`,
					left: '4px',
					right: '4px',
					display: 'flex',
					flexDirection: 'row',
					gap: group.length > 2 ? '0px' : '4px', // No gap if more than 2 boxes (they'll overlap)
					alignItems: 'flex-start',
					width: 'calc(100% - 8px)',
					maxWidth: 'calc(100% - 8px)',
					boxSizing: 'border-box',
				},
				earliestStartMinutes,
			};
		}
		
		// Calculate individual appointment style (for boxes within a flex container)
		function getAppointmentStyle(apt, overlapIndex, overlapCount, earliestStartMinutes) {
			const [startHour, startMin] = (apt.time || '08:00').split(':').map(Number);
			const startMinutes = startHour * 60 + startMin;
			const duration = apt.service_duration || 60;
			
			// Responsive slot height based on screen size
			const isMobile = window.innerWidth <= 768;
			const isSmallMobile = window.innerWidth <= 480;
			const SLOT_HEIGHT = isSmallMobile ? 75 : (isMobile ? 80 : 90);
			const PIXELS_PER_MINUTE = SLOT_HEIGHT / 30;
			const baseStartMinutes = earliestStartMinutes !== undefined ? earliestStartMinutes : (8 * 60);
			const offsetMinutes = Math.max(0, startMinutes - baseStartMinutes);
			const offsetPx = offsetMinutes * PIXELS_PER_MINUTE;
			
			// Calculate height in pixels based on duration
			const heightPx = duration * PIXELS_PER_MINUTE;
			const minHeightPx = isSmallMobile ? 75 : (isMobile ? 80 : 90); // Minimum height to fit all information
			
			// If more than 2 boxes, make them overlap using negative margins
			if (overlapCount > 2) {
				const boxWidth = 150;
				const columnWidth = 312; // Column width (fits 2 boxes max)
				const padding = 8; // Total padding
				const availableWidth = columnWidth - padding; // 304px
				
				// Calculate how much overlap is needed to fit all boxes
				const totalBoxWidth = overlapCount * boxWidth;
				const neededOverlap = totalBoxWidth - availableWidth;
				const overlapAmount = Math.max(20, Math.ceil(neededOverlap / (overlapCount - 1))); // At least 20px overlap
				
				// Use negative margin to create overlap, except for the first box
				const marginLeft = overlapIndex > 0 ? `-${overlapAmount}px` : '0px';
				
				return {
					flex: '0 0 150px', // Fixed width
					width: `${boxWidth}px`,
					minWidth: `${boxWidth}px`,
					maxWidth: `${boxWidth}px`,
					marginLeft: marginLeft,
					minHeight: `${minHeightPx}px`,
					height: `${Math.max(minHeightPx, Math.round(heightPx))}px`,
					marginTop: `${Math.round(offsetPx)}px`,
					boxSizing: 'border-box',
					zIndex: 10 + overlapIndex, // Stack overlapping boxes
					position: 'relative',
				};
			} else {
				// Normal flex layout for 2 or fewer boxes (no overlap needed)
				return {
					flex: '0 0 150px', // Fixed width for 2 or fewer boxes
					width: '150px',
					minWidth: '150px',
					maxWidth: '150px',
					minHeight: `${minHeightPx}px`,
					height: `${Math.max(minHeightPx, Math.round(heightPx))}px`,
					marginTop: `${Math.round(offsetPx)}px`,
					boxSizing: 'border-box',
				};
			}
		}

		// Check if mobile view
		function isMobileView() {
			return window.innerWidth <= 768;
		}

		// Toggle day expansion
		function toggleDayExpansion(dateStr) {
			setExpandedDays(prev => ({
				...prev,
				[dateStr]: !prev[dateStr]
			}));
		}

		// Initialize expanded days - today should be expanded by default on mobile
		useEffect(() => {
			if (isMobileView() && currentWeekStart) {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				// Format date as YYYY-MM-DD (same format as formatDateLocal)
				const year = today.getFullYear();
				const month = String(today.getMonth() + 1).padStart(2, '0');
				const day = String(today.getDate()).padStart(2, '0');
				const todayStr = `${year}-${month}-${day}`;
				setExpandedDays(prev => {
					// Only set if not already initialized
					if (Object.keys(prev).length === 0) {
						return { [todayStr]: true };
					}
					return prev;
				});
			}
		}, [currentWeekStart]);

		// Render calendar view
		// Format date for display
		function formatDateDisplay(date) {
			const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
			return date.toLocaleDateString('en-US', options);
		}

		// Get filtered appointments (used by both list and day views)
		function getFilteredAppointments() {
			// First filter by button filters
			const buttonFiltered = filterAppointmentsByButtons(appointments);
			// Then filter by search
			return filterAppointmentsBySearch(buttonFiltered);
		}

		// Get appointments for a specific date
		function getAppointmentsForDay(date) {
			const dateStr = formatDateLocal(date);
			const filtered = getFilteredAppointments();
			return filtered.filter(apt => apt.date === dateStr);
		}

		// Render day view
		function renderDayView() {
			if (!selectedDay) {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				setSelectedDay(today);
				return el('div', null, 'Loading...');
			}

			const dayAppointments = getAppointmentsForDay(selectedDay);
			const timeSlots = generateTimeSlots();
			
			// Group appointments by staff member
			const staffGroups = {};
			dayAppointments.forEach(apt => {
				const staffId = apt.staff || 'no-staff';
				const staffName = apt.staff_name || 'No Staff Assigned';
				const staffPhoto = apt.staff_photo || 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#e0e0e0"/><circle cx="24" cy="18" r="9" fill="#999"/><path d="M24 30c-6 0-12 3-12 6v3h24v-3c0-3-6-6-12-6z" fill="#999"/></svg>');
				
				if (!staffGroups[staffId]) {
					staffGroups[staffId] = {
						staffId: staffId,
						staffName: staffName,
						staffPhoto: staffPhoto,
						appointments: []
					};
				}
				staffGroups[staffId].appointments.push(apt);
			});

			// Sort appointments within each staff group by time
			Object.keys(staffGroups).forEach(staffId => {
				staffGroups[staffId].appointments.sort((a, b) => {
					return (a.time || '').localeCompare(b.time || '');
				});
			});

			const staffList = Object.values(staffGroups);

			// Navigation functions
			function navigateDay(days) {
				const newDate = new Date(selectedDay);
				newDate.setDate(newDate.getDate() + days);
				newDate.setHours(0, 0, 0, 0);
				setSelectedDay(newDate);
			}

			return el(
				'div',
				{ className: 'mbc-admin-day-view' },
				el(
					'div',
					{ className: 'mbc-day-view-header' },
					el(
						'div',
						{ className: 'mbc-day-nav-group' },
						el('button',
							{
								type: 'button',
								className: 'mbc-day-nav-button',
								onClick: () => navigateDay(-1),
							},
							'← Previous Day'
						),
						el('div', { className: 'mbc-day-date-selector' },
							el('input', {
								type: 'date',
								value: formatDateLocal(selectedDay),
								onChange: (e) => {
									const newDate = new Date(e.target.value + 'T00:00:00');
									newDate.setHours(0, 0, 0, 0);
									setSelectedDay(newDate);
								},
								onClick: (e) => e.target.showPicker(),
								onFocus: (e) => e.target.showPicker(),
								className: 'mbc-day-date-input'
							}),
							el('h2', { className: 'mbc-day-display' }, formatDateDisplay(selectedDay))
						),
						el('button',
							{
								type: 'button',
								className: 'mbc-day-nav-button',
								onClick: () => navigateDay(1),
							},
							'Next Day →'
						)
					),
					el(
						'div',
						{ className: 'mbc-calendar-view-toggle' },
						el('span', { className: 'mbc-view-toggle-label' }, wp.i18n.__('View', 'mpeti-booking-calendar')),
						el('button',
							{
								type: 'button',
								className: `mbc-view-toggle-btn ${viewMode === 'calendar' ? 'is-active' : ''}`,
								onClick: () => setViewMode('calendar'),
								title: wp.i18n.__('Week View', 'mpeti-booking-calendar'),
							},
							el('span', { className: 'dashicons dashicons-calendar-alt' })
						),
						el('button',
							{
								type: 'button',
								className: `mbc-view-toggle-btn ${viewMode === 'day' ? 'is-active' : ''}`,
								onClick: () => setViewMode('day'),
								title: wp.i18n.__('Day View', 'mpeti-booking-calendar'),
							},
							el('span', { className: 'dashicons dashicons-calendar' })
						)
					)
				),
				staffList.length > 0 ? el(
					'div',
					{ className: 'mbc-day-view-container' },
					el(
						'div',
						{ className: 'mbc-day-time-column' },
						el('div', { className: 'mbc-day-time-header' }, ''),
						timeSlots.map(slot => 
							el('div', { key: slot, className: 'mbc-day-time-slot' }, 
								formatTime(slot)
							)
						)
					),
					el(
						'div',
						{ className: 'mbc-day-staff-grid' },
						staffList.map((staffGroup) => {
							const staffAppointments = staffGroup.appointments;
							const overlapGroups = groupOverlappingAppointments(staffAppointments);
							
							return el(
								'div',
								{ key: staffGroup.staffId, className: 'mbc-day-staff-column' },
								el(
									'div',
									{ className: 'mbc-day-staff-header' },
									el('img', {
										className: 'mbc-day-staff-avatar',
										src: staffGroup.staffPhoto,
										alt: staffGroup.staffName
									}),
									el('div', { className: 'mbc-day-staff-name' }, staffGroup.staffName)
								),
								el(
									'div',
									{ className: 'mbc-day-staff-time-grid' },
									timeSlots.map(slot => 
										el('div', { key: slot, className: 'mbc-day-time-row' })
									),
									el('div', { className: 'mbc-day-staff-appointments' },
										overlapGroups.map((group, groupIndex) => {
											const { style: containerStyle, earliestStartMinutes } = getAppointmentContainerStyle(group);
											const overlapCount = group.length;
											return el(
												'div',
												{
													key: `group-${groupIndex}`,
													className: 'mbc-calendar-appointment-group',
													style: containerStyle
												},
												group.map((apt, aptIndex) => {
													const style = getAppointmentStyle(apt, aptIndex, overlapCount, earliestStartMinutes);
													return renderCalendarAppointment(apt, style);
												})
											);
										})
									)
								)
							);
						})
					)
				) : el(
					'div',
					{ className: 'mbc-day-no-appointments' },
					el('p', null, `No appointments found for ${formatDateDisplay(selectedDay)}`)
				)
			);
		}

		function renderCalendarView() {
			if (!currentWeekStart) {
				// Initialize if not set
				const today = new Date();
				const day = today.getDay();
				const diff = today.getDate() - day + (day === 0 ? -6 : 1);
				const monday = new Date(today.setDate(diff));
				monday.setHours(0, 0, 0, 0);
				setCurrentWeekStart(monday);
				return el('div', null, 'Loading...');
			}
			
			const weekDates = getWeekDates(currentWeekStart);
			const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
			const timeSlots = generateTimeSlots();
			
			return el(
				'div',
				{ className: 'mbc-admin-calendar-view' },
				el(
					'div',
					{ className: 'mbc-calendar-week-header' },
					el(
						'div',
						{ className: 'mbc-week-nav-group' },
						el('button',
							{
								type: 'button',
								className: 'mbc-week-nav-button',
								onClick: () => navigateWeek(-1),
							},
							'← Previous Week'
						),
						el('h2', { className: 'mbc-week-range' }, formatWeekRange(currentWeekStart)),
						el('button',
							{
								type: 'button',
								className: 'mbc-week-nav-button',
								onClick: () => navigateWeek(1),
							},
							'Next Week →'
						)
					),
					el(
						'div',
						{ className: 'mbc-calendar-view-toggle' },
						el('span', { className: 'mbc-view-toggle-label' }, wp.i18n.__('View', 'mpeti-booking-calendar')),
						el('button',
							{
								type: 'button',
								className: `mbc-view-toggle-btn ${viewMode === 'calendar' ? 'is-active' : ''}`,
								onClick: () => setViewMode('calendar'),
								title: wp.i18n.__('Week View', 'mpeti-booking-calendar'),
							},
							el('span', { className: 'dashicons dashicons-calendar-alt' })
						),
						el('button',
							{
								type: 'button',
								className: `mbc-view-toggle-btn ${viewMode === 'day' ? 'is-active' : ''}`,
								onClick: () => setViewMode('day'),
								title: wp.i18n.__('Day View', 'mpeti-booking-calendar'),
							},
							el('span', { className: 'dashicons dashicons-calendar' })
						)
					)
				),
				el(
					'div',
					{ className: 'mbc-calendar-week-container' },
					el(
						'div',
						{ className: 'mbc-calendar-time-column' },
						el('div', { className: 'mbc-calendar-time-header' }, ''),
						timeSlots.map(slot => 
							el('div', { key: slot, className: 'mbc-calendar-time-slot' }, 
								formatTime(slot)
							)
						)
					),
					el(
						'div',
						{ className: 'mbc-calendar-week-grid' },
						weekDates.map((date, index) => {
							const dateStr = formatDateLocal(date);
							const dayAppointments = getAppointmentsForDate(date);
							const today = new Date();
							today.setHours(0, 0, 0, 0);
							const todayStr = formatDateLocal(today);
							const isToday = dateStr === todayStr;
							const appointmentCount = dayAppointments.length;
							
							// Check if mobile and if day is expanded
							const mobile = isMobileView();
							const isExpanded = mobile ? (expandedDays[dateStr] !== undefined ? expandedDays[dateStr] : isToday) : true;
							
							// Sort appointments by time
							const sortedAppointments = [...dayAppointments].sort((a, b) => (a.time || '').localeCompare(b.time || ''));
							
							// Group overlapping appointments
							const overlapGroups = groupOverlappingAppointments(sortedAppointments);
							
							// Fixed width for all columns: 2 boxes (300px) + 1 gap (4px) + padding (8px) = 312px
							const fixedColumnWidth = 312;
							
							return el(
								'div',
								{ 
									key: dateStr, 
									className: `mbc-calendar-day-column ${isToday ? 'is-today' : ''} ${mobile ? (isExpanded ? 'is-expanded' : 'is-collapsed') : ''}`,
									style: { 
										width: mobile ? '100%' : `${fixedColumnWidth}px`,
										minWidth: mobile ? '100%' : `${fixedColumnWidth}px`,
										maxWidth: mobile ? '100%' : `${fixedColumnWidth}px`,
										flexShrink: 0
									}
								},
								el('div', { 
									className: `mbc-calendar-day-header ${mobile ? 'mbc-calendar-day-header-clickable' : ''}`,
									onClick: mobile ? () => toggleDayExpansion(dateStr) : undefined
								},
									el('div', { className: 'mbc-calendar-day-header-left' },
										mobile ? el('div', { className: 'mbc-calendar-day-header-text' },
											el('span', { className: 'mbc-calendar-day-date' }, 
												date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
											),
											' - ',
											el('span', { className: 'mbc-calendar-day-name' }, weekDays[index])
										) : el('div', null,
											el('div', { className: 'mbc-calendar-day-name' }, weekDays[index]),
											el('div', { className: 'mbc-calendar-day-date' }, 
												date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
											)
										)
									),
									mobile ? el('div', { className: 'mbc-calendar-day-header-right' },
										el('span', { className: 'mbc-calendar-day-appointment-count' }, appointmentCount),
										el('span', { 
											className: `mbc-calendar-day-toggle ${isExpanded ? 'is-expanded' : ''}` 
										}, isExpanded ? '▼' : '▶')
									) : null
								),
								isExpanded ? el('div', { className: 'mbc-calendar-day-time-grid' },
									timeSlots.map(slot => 
										el('div', { 
											key: slot, 
											className: 'mbc-calendar-time-row',
											'data-time': slot
										}, mobile ? el('span', { className: 'mbc-calendar-time-row-label' }, formatTime(slot)) : null)
									),
									el('div', { className: 'mbc-calendar-day-appointments' },
										overlapGroups.map((group, groupIndex) => {
											const { style: containerStyle, earliestStartMinutes } = getAppointmentContainerStyle(group);
											const overlapCount = group.length;
											return el(
												'div',
												{
													key: `group-${groupIndex}`,
													className: 'mbc-calendar-appointment-group',
													style: containerStyle
												},
												group.map((apt, aptIndex) => {
													const style = getAppointmentStyle(apt, aptIndex, overlapCount, earliestStartMinutes);
													return renderCalendarAppointment(apt, style);
												})
											);
										})
									)
								) : null
							);
						})
					)
				)
			);
		}

		const statusOptions = [
			{ label: 'Pending', value: 'pending' },
			{ label: 'Confirmed', value: 'confirmed' },
			{ label: 'Cancelled', value: 'cancelled' },
		];

		const grouped = groupAppointments();

		return el(
			'div',
			{ className: 'mbc-admin-appointments-list-view' },
			renderAppointmentModal(),
			el(
				'div',
				{ className: 'mbc-view-toggle' },
				el('button',
					{
						type: 'button',
						className: `mbc-view-toggle-btn ${viewMode === 'list' ? 'is-active' : ''}`,
						onClick: () => setViewMode('list'),
						title: wp.i18n.__('List View', 'mpeti-booking-calendar'),
					},
					el('span', { className: 'dashicons dashicons-list-view' })
				),
				el('button',
					{
						type: 'button',
						className: `mbc-view-toggle-btn ${viewMode === 'calendar' ? 'is-active' : ''}`,
						onClick: () => setViewMode('calendar'),
						title: wp.i18n.__('Week View', 'mpeti-booking-calendar'),
					},
					el('span', { className: 'dashicons dashicons-calendar-alt' })
				)
			),
			el(
				'div',
				{ className: 'mbc-admin-search-group' },
				el(
					'button',
					{
						type: 'button',
						className: 'mbc-admin-search-header',
						onClick: () => setSearchExpanded(!searchExpanded),
					},
					el('span', { className: 'mbc-search-group-title' }, wp.i18n.__('Search', 'mpeti-booking-calendar')),
					el('span', { 
						className: `mbc-search-group-toggle ${searchExpanded ? 'is-expanded' : ''}` 
					}, '▼')
				),
				searchExpanded ? el(
					'div',
					{ className: 'mbc-admin-search-fields' },
					el('div', { className: 'mbc-search-fields-row' },
						el('div', { className: 'mbc-search-field-group' },
							el('label', { className: 'mbc-search-label' }, wp.i18n.__('Name', 'mpeti-booking-calendar')),
							el('input', {
								type: 'text',
								className: 'mbc-search-input',
								placeholder: wp.i18n.__('Filter by customer name...', 'mpeti-booking-calendar'),
								value: searchFilters.name,
								onChange: (e) => setSearchFilters({ ...searchFilters, name: e.target.value }),
							})
						),
						el('div', { className: 'mbc-search-field-group' },
							el('label', { className: 'mbc-search-label' }, wp.i18n.__('Email', 'mpeti-booking-calendar')),
							el('input', {
								type: 'email',
								className: 'mbc-search-input',
								placeholder: wp.i18n.__('Filter by email...', 'mpeti-booking-calendar'),
								value: searchFilters.email,
								onChange: (e) => setSearchFilters({ ...searchFilters, email: e.target.value }),
							})
						),
						el('div', { className: 'mbc-search-field-group' },
							el('label', { className: 'mbc-search-label' }, wp.i18n.__('Date', 'mpeti-booking-calendar')),
							el('input', {
								type: 'date',
								className: 'mbc-search-input',
								value: searchFilters.date,
								onClick: (e) => {
									// Ensure the date picker opens
									e.target.showPicker && e.target.showPicker();
								},
								onFocus: (e) => {
									// Try to open picker on focus as well
									if (e.target.showPicker) {
										try {
											e.target.showPicker();
										} catch (err) {
											// showPicker might not be supported or user interaction required
										}
									}
								},
								onChange: (e) => {
									setSearchFilters({ ...searchFilters, date: e.target.value });
								},
							}),
							searchFilters.date ? el(
								'button',
								{
									type: 'button',
									className: 'mbc-search-clear-date',
									onClick: () => {
										setSearchFilters({ ...searchFilters, date: '' });
									},
									title: wp.i18n.__('Clear date', 'mpeti-booking-calendar'),
								},
								'×'
							) : null
						)
					),
					el('div', { className: 'mbc-search-actions' },
						el('button',
							{
								type: 'button',
								className: 'mbc-search-button',
								onClick: () => setActiveSearchFilters({ ...searchFilters }),
							},
							wp.i18n.__('Search', 'mpeti-booking-calendar')
						),
						(activeSearchFilters.name || activeSearchFilters.email || activeSearchFilters.date) ? el(
							'button',
							{
								type: 'button',
								className: 'mbc-search-clear-all',
								onClick: () => {
									const cleared = { name: '', email: '', date: '' };
									setSearchFilters(cleared);
									setActiveSearchFilters(cleared);
								},
							},
							wp.i18n.__('Clear All', 'mpeti-booking-calendar')
						) : null
					)
				) : null
			),
			el(
				'div',
				{ className: 'mbc-admin-filters-container' },
				el(
					'button',
					{
						type: 'button',
						className: 'mbc-admin-filters-header',
						onClick: () => setFiltersExpanded(!filtersExpanded),
					},
					el('span', { className: 'mbc-filters-group-title' }, wp.i18n.__('Filters', 'mpeti-booking-calendar')),
					el('span', { 
						className: `mbc-filters-group-toggle ${filtersExpanded ? 'is-expanded' : ''}` 
					}, '▼')
				),
				filtersExpanded ? el(
					'div',
					{ className: 'mbc-admin-filters-content' },
					el(
						'div',
						{ className: 'mbc-admin-filters' },
						renderFilterGroup('Status', 'status', statusOptions),
						renderFilterGroup('Staff', 'staff', staffOptions),
						renderFilterGroup('Service', 'service', serviceOptions)
					)
				) : null
			),
			viewMode === 'list' ? (
				loading ? el(Spinner, null) : (
					grouped.length > 0 ? (
						el('div', { className: 'mbc-admin-appointments-container' },
							grouped.map(renderStaffGroup)
						)
					) : (
						el('div', { className: 'mbc-admin-no-appointments' }, 
							wp.i18n.__('No upcoming appointments found.', 'mpeti-booking-calendar')
						)
					)
				)
			) : viewMode === 'day' ? (
				loading ? el(Spinner, null) : renderDayView()
			) : (
				loading ? el(Spinner, null) : renderCalendarView()
			)
		);
	}

	document.addEventListener('DOMContentLoaded', () => {
		const root = document.getElementById('mbc-admin-calendar-root');
		if (root) {
			render(el(AppointmentsList), root);
		}
	});
})(window.wp, window.MBCAdmin || {});
