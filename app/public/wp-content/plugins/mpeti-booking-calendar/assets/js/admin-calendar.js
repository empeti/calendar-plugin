(function( wp, MBCAdmin ) {
	const { createElement: el, useState, useEffect } = wp.element;
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
		const [filters, setFilters] = useState({ staff: '', service: '', status: '' });
		const [staffOptions, setStaffOptions] = useState([{ label: 'All', value: '' }]);
		const [serviceOptions, setServiceOptions] = useState([{ label: 'All', value: '' }]);

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
						const options = [{ label: 'All', value: '' }];
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
						const options = [{ label: 'All', value: '' }];
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
			if (filters.staff) params.append('staff', filters.staff);
			if (filters.service) params.append('service', filters.service);
			if (filters.status) params.append('status', filters.status);
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

		// Group appointments by staff member, then by date
		function groupAppointments() {
			const grouped = {};
			
			appointments.forEach((apt) => {
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

		function renderAppointment(apt) {
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
						)
					)
				)
			);
		}

		function renderStaffGroup(group) {
			return el(
				'div',
				{ key: group.staffName, className: 'mbc-admin-staff-group' },
				el('h2', 
					{ 
						className: 'mbc-admin-staff-header',
						style: { borderLeftColor: group.staffColor }
					}, 
					group.staffName
				),
				group.dates.map((dateGroup) =>
					el(
						'div',
						{ key: dateGroup.date, className: 'mbc-admin-date-group' },
						el('h3', { className: 'mbc-admin-date-header' }, formatDate(dateGroup.date)),
						el('div', { className: 'mbc-admin-appointments-list' },
							dateGroup.appointments.map(renderAppointment)
						)
					)
				)
			);
		}

		const grouped = groupAppointments();

		return el(
			'div',
			{ className: 'mbc-admin-appointments-list-view' },
			el(
				'div',
				{ className: 'mbc-admin-filters' },
				el(SelectControl, {
					label: 'Status',
					value: filters.status,
					onChange: (v) => setFilters({ ...filters, status: v }),
					options: [
						{ label: 'All', value: '' },
						{ label: 'Pending', value: 'pending' },
						{ label: 'Confirmed', value: 'confirmed' },
						{ label: 'Cancelled', value: 'cancelled' },
					],
				}),
				el(SelectControl, {
					label: 'Staff',
					value: filters.staff,
					onChange: (v) => setFilters({ ...filters, staff: v }),
					options: staffOptions,
				}),
				el(SelectControl, {
					label: 'Service',
					value: filters.service,
					onChange: (v) => setFilters({ ...filters, service: v }),
					options: serviceOptions,
				})
			),
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
		);
	}

	document.addEventListener('DOMContentLoaded', () => {
		const root = document.getElementById('mbc-admin-calendar-root');
		if (root) {
			render(el(AppointmentsList), root);
		}
	});
})(window.wp, window.MBCAdmin || {});
