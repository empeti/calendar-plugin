(function( wp, MBCAdmin ) {
	const { createElement: el, useState, useEffect } = wp.element;
	const { render } = wp.element;
	const { Button, SelectControl, Spinner, Panel, PanelBody } = wp.components;
	const apiFetch = wp.apiFetch;

	function startOfMonth(date) {
		return new Date(date.getFullYear(), date.getMonth(), 1);
	}
	function endOfMonth(date) {
		return new Date(date.getFullYear(), date.getMonth() + 1, 0);
	}
	function formatDate(date) {
		return date.toISOString().slice(0, 10);
	}

	function CalendarApp() {
		const [current, setCurrent] = useState(new Date());
		const [appointments, setAppointments] = useState([]);
		const [loading, setLoading] = useState(false);
		const [filters, setFilters] = useState({ staff: '', service: '', status: '' });
		const [selectedDate, setSelectedDate] = useState(null);

		useEffect(() => {
			loadAppointments();
		}, [current, filters]);

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
				.then(setAppointments)
				.finally(() => setLoading(false));
		}

		const start = startOfMonth(current);
		const end = endOfMonth(current);
		const days = [];
		for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
			const dateStr = formatDate(d);
			const dayAppointments = appointments.filter((a) => a.date === dateStr);
			days.push({
				date: dateStr,
				count: dayAppointments.length,
				items: dayAppointments,
			});
		}

		function renderDay(day) {
			const color = day.items[0]?.staff_color || day.items[0]?.service_color || '#0073aa';
			return el(
				'div',
				{
					key: day.date,
					className: 'mbc-admin-day',
					style: { borderColor: color },
					onClick: () => setSelectedDate(day),
				},
				el('div', { className: 'mbc-admin-day-date' }, day.date),
				el('div', { className: 'mbc-admin-day-count' }, `${day.count} ${day.count === 1 ? 'appt' : 'appts'}`)
			);
		}

		function renderSidebar() {
			if (!selectedDate) {
				return el('div', { className: 'mbc-admin-sidebar-placeholder' }, 'Select a date to view appointments.');
			}
			return el(
				'div',
				{ className: 'mbc-admin-sidebar' },
				el('h3', null, selectedDate.date),
				selectedDate.items.map((item) =>
					el(
						'div',
						{ key: item.id, className: 'mbc-admin-appointment' },
						el('div', null, `${item.time} - ${item.customer}`),
						el('div', null, `${MBCAdmin.i18n.status || 'Status'}: ${item.status}`),
						el(
							'a',
							{ href: `${window.location.origin}/wp-admin/post.php?post=${item.id}&action=edit` },
							wp.i18n.__('Edit', 'mpeti-booking-calendar')
						)
					)
				)
			);
		}

		return el(
			'div',
			{ className: 'mbc-admin-calendar-app' },
			el(
				'div',
				{ className: 'mbc-admin-toolbar' },
				el(Button, { isSecondary: true, onClick: () => setCurrent(new Date(current.getFullYear(), current.getMonth() - 1, 1)) }, '<'),
				el('strong', null, current.toLocaleString(undefined, { month: 'long', year: 'numeric' })),
				el(Button, { isSecondary: true, onClick: () => setCurrent(new Date(current.getFullYear(), current.getMonth() + 1, 1)) }, '>')
			),
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
					options: [
						{ label: 'All', value: '' },
						// Extend: fetch staff via REST to populate.
					],
				}),
				el(SelectControl, {
					label: 'Service',
					value: filters.service,
					onChange: (v) => setFilters({ ...filters, service: v }),
					options: [
						{ label: 'All', value: '' },
						// Extend: fetch services via REST to populate.
					],
				})
			),
			loading ? el(Spinner, null) : el('div', { className: 'mbc-admin-grid' }, days.map(renderDay)),
			el(Panel, null, el(PanelBody, { title: 'Appointments' }, renderSidebar()))
		);
	}

	document.addEventListener('DOMContentLoaded', () => {
		const root = document.getElementById('mbc-admin-calendar-root');
		if (root) {
			render(el(CalendarApp), root);
		}
	});
})(window.wp, window.MBCAdmin || {});

