import { db } from './firebase-init.js';
import {
  collection,
  query,
  orderBy,
  where,
  onSnapshot,
  getDocs,
  getDoc,
  doc,
  setDoc,
  updateDoc,
  deleteDoc,
  serverTimestamp,
  increment
} from 'https://www.gstatic.com/firebasejs/12.17.0/firebase-firestore.js';

const credentials = { userId: 'CM1', password: '341' };
let loggedInUser = null;
let tripsData = [];
let ticketsData = [];
let requestsData = [];
let listenersStarted = false;

const screens = {
  login: document.getElementById('loginScreen'),
  app: document.getElementById('appScreen')
};

const sectionIds = [
  'dashboardView',
  'tripsView',
  'ticketsView',
  'requestsView'
];

const navButtons = {
  dashboard: document.getElementById('navDashboard'),
  trips: document.getElementById('navTrips'),
  tickets: document.getElementById('navTickets'),
  requests: document.getElementById('navRequests')
};

const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
const dashboardGreeting = document.getElementById('dashboardGreeting');

const dashboardFields = {
  todaysTrips: document.getElementById('todaysTrips'),
  completedTrips: document.getElementById('completedTrips'),
  bookedSeats: document.getElementById('bookedSeats'),
  availableSeats: document.getElementById('availableSeats'),
  earnings: document.getElementById('totalEarnings'),
  requestCount: document.getElementById('totalRequests')
};

const tripTableBody = document.getElementById('tripsTableBody');
const tripForm = document.getElementById('tripCreateForm');
const tripEditSection = document.getElementById('tripEditSection');
const tripEditForm = document.getElementById('tripEditForm');
const tripEditMessage = document.getElementById('tripEditMessage');
const tripTableMessage = document.getElementById('tripTableMessage');

const ticketTableBody = document.getElementById('ticketsTableBody');
const ticketForm = document.getElementById('ticketCreateForm');
const ticketEditSection = document.getElementById('ticketEditSection');
const ticketEditForm = document.getElementById('ticketEditForm');
const ticketEditMessage = document.getElementById('ticketEditMessage');
const ticketTableMessage = document.getElementById('ticketTableMessage');
const tripSelectForTicket = document.getElementById('ticket_trip_id');

const requestForm = document.getElementById('requestCreateForm');
const requestEditSection = document.getElementById('requestEditSection');
const requestEditForm = document.getElementById('requestEditForm');
const requestTableBody = document.getElementById('requestsTableBody');
const requestMessage = document.getElementById('requestMessage');

const tripsCollection = collection(db, 'trips');
const ticketsCollection = collection(db, 'tickets');
const requestsCollection = collection(db, 'requests');

function formatDate(date) {
  return date.toLocaleDateString('en-CA');
}

function formatDateTime(date) {
  return `${date.toLocaleDateString('en-CA')} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
}

function parseTime(timeString) {
  const [hours, minutes] = (timeString || '00:00').split(':').map(Number);
  return (hours || 0) * 60 + (minutes || 0);
}

function showScreen(key) {
  Object.values(screens).forEach(section => section.classList.remove('screen--active'));
  screens[key].classList.add('screen--active');
}

function showSection(sectionId) {
  sectionIds.forEach(id => document.getElementById(id).classList.remove('view-active'));
  if (sectionId) {
    document.getElementById(sectionId).classList.add('view-active');
  }

  Object.values(navButtons).forEach(btn => btn.classList.remove('active'));
  if (sectionId === 'dashboardView') navButtons.dashboard.classList.add('active');
  if (sectionId === 'tripsView') navButtons.trips.classList.add('active');
  if (sectionId === 'ticketsView') navButtons.tickets.classList.add('active');
  if (sectionId === 'requestsView') navButtons.requests.classList.add('active');
}

function showTemporaryMessage(element, text, type = 'info') {
  element.textContent = text;
  element.className = type === 'error' ? 'message message--error' : 'message message--success';
  setTimeout(() => {
    element.textContent = '';
    element.className = 'message';
  }, 4100);
}

async function handleLogin(event) {
  event.preventDefault();
  const userId = loginForm.userid.value.trim();
  const password = loginForm.password.value.trim();

  if (userId === credentials.userId && password === credentials.password) {
    loggedInUser = credentials.userId;
    loginError.textContent = '';
    dashboardGreeting.textContent = `Welcome back, ${loggedInUser}`;
    showScreen('app');
    showSection('dashboardView');
    if (!listenersStarted) {
      await seedSampleTrips();
      startRealtimeListeners();
      startClock();
      listenersStarted = true;
    }
  } else {
    loginError.textContent = 'Invalid credentials. Use AD1 / 141.';
  }
}

async function seedSampleTrips() {
  const sampleQuery = query(tripsCollection, orderBy('travel_date'), orderBy('departure_time'));
  const snapshot = await getDocs(sampleQuery);
  if (!snapshot.empty) {
    return;
  }

  const today = new Date();
  const todayLabel = formatDate(today);
  const nextDayLabel = formatDate(new Date(today.getTime() + 86400000));
  const sampleTrips = [
    {
      id: `T-${todayLabel}-A`,
      origin: 'Dhaka',
      destination: 'Chattogram',
      bus_id: 'B100',
      travel_date: todayLabel,
      departure_time: '09:00',
      arrival_time: '13:00',
      fare: 420,
      total_seats: 40,
      available_seats: 40
    },
    {
      id: `T-${todayLabel}-B`,
      origin: 'Dhaka',
      destination: 'Sylhet',
      bus_id: 'B101',
      travel_date: todayLabel,
      departure_time: '14:00',
      arrival_time: '18:00',
      fare: 480,
      total_seats: 40,
      available_seats: 40
    },
    {
      id: `T-${todayLabel}-C`,
      origin: 'Dhaka',
      destination: 'Rajshahi',
      bus_id: 'B102',
      travel_date: todayLabel,
      departure_time: '19:30',
      arrival_time: '23:30',
      fare: 500,
      total_seats: 40,
      available_seats: 40
    },
    {
      id: `T-${nextDayLabel}-A`,
      origin: 'Dhaka',
      destination: 'Khulna',
      bus_id: 'B103',
      travel_date: nextDayLabel,
      departure_time: '08:00',
      arrival_time: '12:00',
      fare: 440,
      total_seats: 40,
      available_seats: 40
    }
  ];

  for (const trip of sampleTrips) {
    const tripRef = doc(tripsCollection, trip.id);
    await setDoc(tripRef, {
      trip_id: trip.id,
      origin: trip.origin,
      destination: trip.destination,
      bus_id: trip.bus_id,
      travel_date: trip.travel_date,
      departure_time: trip.departure_time,
      arrival_time: trip.arrival_time,
      fare: trip.fare,
      total_seats: trip.total_seats,
      available_seats: trip.available_seats,
      created_at: serverTimestamp()
    });
  }
}

function bindButtons() {
  navButtons.dashboard.addEventListener('click', () => showSection('dashboardView'));
  navButtons.trips.addEventListener('click', () => showSection('tripsView'));
  navButtons.tickets.addEventListener('click', () => showSection('ticketsView'));
  navButtons.requests.addEventListener('click', () => showSection('requestsView'));

  document.getElementById('logoutBtn').addEventListener('click', () => {
    loggedInUser = null;
    showScreen('login');
    loginForm.reset();
    tripEditSection.classList.add('hidden');
    ticketEditSection.classList.add('hidden');
    requestEditSection.classList.add('hidden');
  });
}

function buildReference(value) {
  return value ? value.trim() : '';
}

function getCurrentTimeMinutes() {
  const now = new Date();
  return now.getHours() * 60 + now.getMinutes();
}

function buildTripRow(trip) {
  return `
    <tr>
      <td>${trip.trip_id}</td>
      <td>${trip.travel_date}</td>
      <td>${trip.departure_time}</td>
      <td>${trip.arrival_time}</td>
      <td>${trip.bus_id}</td>
      <td>${trip.origin}</td>
      <td>${trip.destination}</td>
      <td>${trip.fare}</td>
      <td>${trip.available_seats}</td>
      <td class="actions">
        <button data-action="edit-trip" data-id="${trip.trip_id}">Edit</button>
        <button data-action="delete-trip" data-id="${trip.trip_id}">Delete</button>
      </td>
    </tr>
  `;
}

function buildTicketRow(ticket) {
  return `
    <tr>
      <td>${ticket.ticket_id}</td>
      <td>${ticket.trip_id}</td>
      <td>${ticket.travel_date || ''}</td>
      <td>${ticket.seat_number}</td>
      <td>${ticket.passenger_name}</td>
      <td>${ticket.phone}</td>
      <td>${ticket.email}</td>
      <td>${ticket.boarding_point}</td>
      <td>${ticket.dropping_point}</td>
      <td>${ticket.payment_method}</td>
      <td>${ticket.fare ?? ''}</td>
      <td>${ticket.booking_time ?? ''}</td>
      <td class="actions">
        <button data-action="edit-ticket" data-id="${ticket.ticket_id}">Edit</button>
        <button data-action="delete-ticket" data-id="${ticket.ticket_id}">Delete</button>
      </td>
    </tr>
  `;
}

function buildRequestRow(request) {
  const created = request.created_at && request.created_at.seconds ? new Date(request.created_at.seconds * 1000).toLocaleString() : '';
  return `
    <tr>
      <td>${request.request_id}</td>
      <td>${request.subject}</td>
      <td>${request.details}</td>
      <td>${request.status}</td>
      <td>${created}</td>
      <td class="actions">
        <button data-action="edit-request" data-id="${request.request_id}">Edit</button>
        <button data-action="delete-request" data-id="${request.request_id}">Delete</button>
      </td>
    </tr>
  `;
}

async function handleTripCreate(event) {
  event.preventDefault();

  const tripId = buildReference(tripForm.trip_id.value);
  const origin = buildReference(tripForm.origin.value);
  const destination = buildReference(tripForm.destination.value);
  const travelDate = buildReference(tripForm.travel_date.value);
  const departureTime = buildReference(tripForm.departure_time.value);
  const arrivalTime = buildReference(tripForm.arrival_time.value);
  const busId = buildReference(tripForm.bus_id.value);
  const fare = Number(tripForm.fare.value || '0');
  const seats = Number(tripForm.total_seats.value || '40');

  if (!tripId || !origin || !destination || !travelDate || !departureTime || !arrivalTime || !busId || fare <= 0) {
    showTemporaryMessage(tripTableMessage, 'Provide every required trip field before saving.', 'error');
    return;
  }

  try {
    const tripRef = doc(tripsCollection, tripId);
    await setDoc(tripRef, {
      trip_id: tripId,
      origin,
      destination,
      travel_date: travelDate,
      departure_time: departureTime,
      arrival_time: arrivalTime,
      bus_id: busId,
      fare,
      total_seats: seats,
      available_seats: seats,
      created_at: serverTimestamp()
    });
    tripForm.reset();
    showTemporaryMessage(tripTableMessage, 'Trip created successfully.');
  } catch (error) {
    showTemporaryMessage(tripTableMessage, `Trip creation failed: ${error.message}`, 'error');
  }
}

async function handleTicketCreate(event) {
  event.preventDefault();

  const tripId = buildReference(ticketForm.trip_id.value);
  const seatNumber = buildReference(ticketForm.seat_number.value);
  const passengerName = buildReference(ticketForm.passenger_name.value);
  const phone = buildReference(ticketForm.phone.value);
  const email = buildReference(ticketForm.email.value);
  const boardingPoint = buildReference(ticketForm.boarding_point.value);
  const droppingPoint = buildReference(ticketForm.dropping_point.value);
  const paymentMethod = buildReference(ticketForm.payment_method.value);
  const paymentInfo = buildReference(ticketForm.payment_info.value);

  if (!tripId || !seatNumber || !passengerName || !phone || !email || !boardingPoint || !droppingPoint || !paymentMethod || !paymentInfo) {
    showTemporaryMessage(ticketTableMessage, 'Complete all ticket fields before saving.', 'error');
    return;
  }

  try {
    const tripRef = doc(tripsCollection, tripId);
    const tripDoc = await getDoc(tripRef);
    if (!tripDoc.exists()) {
      showTemporaryMessage(ticketTableMessage, 'Selected trip does not exist.', 'error');
      return;
    }

    const trip = tripDoc.data();
    if ((trip.available_seats ?? 0) <= 0) {
      showTemporaryMessage(ticketTableMessage, 'No seats available for this trip.', 'error');
      return;
    }

    const newTicketRef = doc(ticketsCollection);
    const newTicket = {
      ticket_id: newTicketRef.id,
      trip_id: tripId,
      travel_date: trip.travel_date,
      seat_number: seatNumber,
      passenger_name: passengerName,
      phone,
      email,
      boarding_point: boardingPoint,
      dropping_point: droppingPoint,
      payment_method: paymentMethod,
      payment_info: paymentInfo,
      fare: trip.fare,
      booking_time: formatDateTime(new Date()),
      created_at: serverTimestamp(),
      created_by: loggedInUser
    };

    await setDoc(newTicketRef, newTicket);
    await updateDoc(tripRef, {
      available_seats: increment(-1)
    });

    ticketForm.reset();
    showTemporaryMessage(ticketTableMessage, 'Ticket created successfully.');
  } catch (error) {
    showTemporaryMessage(ticketTableMessage, `Ticket creation failed: ${error.message}`, 'error');
  }
}

async function handleRequestCreate(event) {
  event.preventDefault();

  const subject = buildReference(requestForm.subject.value);
  const details = buildReference(requestForm.details.value);

  if (!subject || !details) {
    showTemporaryMessage(requestMessage, 'Add a subject and details for the request.', 'error');
    return;
  }

  try {
    const requestRef = doc(requestsCollection);
    await setDoc(requestRef, {
      request_id: requestRef.id,
      requested_by: loggedInUser,
      subject,
      details,
      status: 'Pending',
      created_at: serverTimestamp(),
      updated_at: serverTimestamp()
    });
    requestForm.reset();
    showTemporaryMessage(requestMessage, 'Request sent to the admin.');
  } catch (error) {
    showTemporaryMessage(requestMessage, `Saving request failed: ${error.message}`, 'error');
  }
}

function renderTripsTable() {
  tripsData.sort((a, b) => {
    if (a.travel_date !== b.travel_date) return a.travel_date.localeCompare(b.travel_date);
    return parseTime(a.departure_time) - parseTime(b.departure_time);
  });

  tripTableBody.innerHTML = tripsData.length
    ? tripsData.map(buildTripRow).join('')
    : '<tr><td colspan="10">No trips available yet.</td></tr>';
  bindTripTableActions();
}

function renderTicketsTable() {
  ticketTableBody.innerHTML = ticketsData.length
    ? ticketsData.map(buildTicketRow).join('')
    : '<tr><td colspan="13">No tickets have been created yet.</td></tr>';
  bindTicketTableActions();
}

function renderRequestsTable() {
  requestTableBody.innerHTML = requestsData.length
    ? requestsData.map(buildRequestRow).join('')
    : '<tr><td colspan="6">No requests found in your history.</td></tr>';
  bindRequestTableActions();
}

function bindTripTableActions() {
  tripTableBody.querySelectorAll('button[data-action="edit-trip"]').forEach(button => {
    button.addEventListener('click', () => loadTripForEdit(button.dataset.id));
  });
  tripTableBody.querySelectorAll('button[data-action="delete-trip"]').forEach(button => {
    button.addEventListener('click', () => deleteTrip(button.dataset.id));
  });
}

function bindTicketTableActions() {
  ticketTableBody.querySelectorAll('button[data-action="edit-ticket"]').forEach(button => {
    button.addEventListener('click', () => loadTicketForEdit(button.dataset.id));
  });
  ticketTableBody.querySelectorAll('button[data-action="delete-ticket"]').forEach(button => {
    button.addEventListener('click', () => deleteTicket(button.dataset.id));
  });
}

function bindRequestTableActions() {
  requestTableBody.querySelectorAll('button[data-action="edit-request"]').forEach(button => {
    button.addEventListener('click', () => loadRequestForEdit(button.dataset.id));
  });
  requestTableBody.querySelectorAll('button[data-action="delete-request"]').forEach(button => {
    button.addEventListener('click', () => deleteRequest(button.dataset.id));
  });
}

async function loadTripForEdit(tripId) {
  const selectedTrip = tripsData.find(trip => trip.trip_id === tripId);
  if (!selectedTrip) {
    showTemporaryMessage(tripTableMessage, 'Trip not found.', 'error');
    return;
  }

  tripEditSection.classList.remove('hidden');
  tripEditForm.trip_id.value = selectedTrip.trip_id;
  tripEditForm.origin.value = selectedTrip.origin;
  tripEditForm.destination.value = selectedTrip.destination;
  tripEditForm.travel_date.value = selectedTrip.travel_date;
  tripEditForm.departure_time.value = selectedTrip.departure_time;
  tripEditForm.arrival_time.value = selectedTrip.arrival_time;
  tripEditForm.bus_id.value = selectedTrip.bus_id;
  tripEditForm.fare.value = selectedTrip.fare;
  tripEditForm.total_seats.value = selectedTrip.total_seats;
}

async function loadTicketForEdit(ticketId) {
  const selectedTicket = ticketsData.find(ticket => ticket.ticket_id === ticketId);
  if (!selectedTicket) {
    showTemporaryMessage(ticketTableMessage, 'Ticket not found.', 'error');
    return;
  }

  ticketEditSection.classList.remove('hidden');
  ticketEditForm.ticket_id.value = selectedTicket.ticket_id;
  ticketEditForm.trip_id.value = selectedTicket.trip_id;
  ticketEditForm.seat_number.value = selectedTicket.seat_number;
  ticketEditForm.passenger_name.value = selectedTicket.passenger_name;
  ticketEditForm.phone.value = selectedTicket.phone;
  ticketEditForm.email.value = selectedTicket.email;
  ticketEditForm.boarding_point.value = selectedTicket.boarding_point;
  ticketEditForm.dropping_point.value = selectedTicket.dropping_point;
  ticketEditForm.payment_method.value = selectedTicket.payment_method;
  ticketEditForm.payment_info.value = selectedTicket.payment_info;
}

async function loadRequestForEdit(requestId) {
  const selectedRequest = requestsData.find(request => request.request_id === requestId);
  if (!selectedRequest) {
    showTemporaryMessage(requestMessage, 'Request not found.', 'error');
    return;
  }

  requestEditSection.classList.remove('hidden');
  requestEditForm.request_id.value = selectedRequest.request_id;
  requestEditForm.subject.value = selectedRequest.subject;
  requestEditForm.details.value = selectedRequest.details;
}

async function deleteTrip(tripId) {
  if (!confirm('Delete this trip? All associated tickets will remain and may point to a removed trip.')) {
    return;
  }

  try {
    await deleteDoc(doc(tripsCollection, tripId));
    showTemporaryMessage(tripTableMessage, 'Trip deleted successfully.');
  } catch (error) {
    showTemporaryMessage(tripTableMessage, `Error deleting trip: ${error.message}`, 'error');
  }
}

async function deleteTicket(ticketId) {
  if (!confirm('Delete this ticket and restore available seats?')) return;
  try {
    const ticketRef = doc(ticketsCollection, ticketId);
    const ticketDoc = await getDoc(ticketRef);
    if (!ticketDoc.exists()) {
      showTemporaryMessage(ticketTableMessage, 'Ticket not found.', 'error');
      return;
    }

    const ticket = ticketDoc.data();
    await deleteDoc(ticketRef);
    if (ticket.trip_id) {
      const tripRef = doc(tripsCollection, ticket.trip_id);
      await updateDoc(tripRef, { available_seats: increment(1) });
    }
    showTemporaryMessage(ticketTableMessage, 'Ticket deleted successfully.');
  } catch (error) {
    showTemporaryMessage(ticketTableMessage, `Error deleting ticket: ${error.message}`, 'error');
  }
}

async function deleteRequest(requestId) {
  if (!confirm('Delete this request?')) return;
  try {
    await deleteDoc(doc(requestsCollection, requestId));
    showTemporaryMessage(requestMessage, 'Request removed.');
  } catch (error) {
    showTemporaryMessage(requestMessage, `Unable to delete request: ${error.message}`, 'error');
  }
}

async function handleTripEdit(event) {
  event.preventDefault();

  const tripId = buildReference(tripEditForm.trip_id.value);
  const origin = buildReference(tripEditForm.origin.value);
  const destination = buildReference(tripEditForm.destination.value);
  const travelDate = buildReference(tripEditForm.travel_date.value);
  const departureTime = buildReference(tripEditForm.departure_time.value);
  const arrivalTime = buildReference(tripEditForm.arrival_time.value);
  const busId = buildReference(tripEditForm.bus_id.value);
  const fare = Number(tripEditForm.fare.value || '0');
  const seats = Number(tripEditForm.total_seats.value || '40');

  if (!tripId || !origin || !destination || !travelDate || !departureTime || !arrivalTime || !busId || fare <= 0) {
    showTemporaryMessage(tripEditMessage, 'Complete every trip field before saving.', 'error');
    return;
  }

  try {
    const tripRef = doc(tripsCollection, tripId);
    await updateDoc(tripRef, {
      origin,
      destination,
      travel_date: travelDate,
      departure_time: departureTime,
      arrival_time: arrivalTime,
      bus_id: busId,
      fare,
      total_seats: seats,
      available_seats: seats
    });
    tripEditSection.classList.add('hidden');
    showTemporaryMessage(tripTableMessage, 'Trip updated successfully.');
  } catch (error) {
    showTemporaryMessage(tripEditMessage, `Trip update failed: ${error.message}`, 'error');
  }
}

async function handleTicketEdit(event) {
  event.preventDefault();
  const ticketId = buildReference(ticketEditForm.ticket_id.value);
  const seatNumber = buildReference(ticketEditForm.seat_number.value);
  const passengerName = buildReference(ticketEditForm.passenger_name.value);
  const phone = buildReference(ticketEditForm.phone.value);
  const email = buildReference(ticketEditForm.email.value);
  const boardingPoint = buildReference(ticketEditForm.boarding_point.value);
  const droppingPoint = buildReference(ticketEditForm.dropping_point.value);
  const paymentMethod = buildReference(ticketEditForm.payment_method.value);
  const paymentInfo = buildReference(ticketEditForm.payment_info.value);

  if (!ticketId || !seatNumber || !passengerName || !phone || !email || !boardingPoint || !droppingPoint || !paymentMethod || !paymentInfo) {
    showTemporaryMessage(ticketEditMessage, 'Complete every ticket field before updating.', 'error');
    return;
  }

  try {
    const ticketRef = doc(ticketsCollection, ticketId);
    await updateDoc(ticketRef, {
      seat_number: seatNumber,
      passenger_name: passengerName,
      phone,
      email,
      boarding_point: boardingPoint,
      dropping_point: droppingPoint,
      payment_method: paymentMethod,
      payment_info: paymentInfo,
      updated_at: serverTimestamp()
    });
    ticketEditSection.classList.add('hidden');
    showTemporaryMessage(ticketTableMessage, 'Ticket updated successfully.');
  } catch (error) {
    showTemporaryMessage(ticketEditMessage, `Ticket update failed: ${error.message}`, 'error');
  }
}

async function handleRequestEdit(event) {
  event.preventDefault();
  const requestId = buildReference(requestEditForm.request_id.value);
  const subject = buildReference(requestEditForm.subject.value);
  const details = buildReference(requestEditForm.details.value);

  if (!requestId || !subject || !details) {
    showTemporaryMessage(requestMessage, 'Complete every request field before updating.', 'error');
    return;
  }

  try {
    const requestRef = doc(requestsCollection, requestId);
    await updateDoc(requestRef, {
      subject,
      details,
      updated_at: serverTimestamp()
    });
    requestEditSection.classList.add('hidden');
    showTemporaryMessage(requestMessage, 'Request updated successfully.');
  } catch (error) {
    showTemporaryMessage(requestMessage, `Unable to update request: ${error.message}`, 'error');
  }
}

function updateTripSelection() {
  tripSelectForTicket.innerHTML = tripsData.length
    ? tripsData.map(trip => `<option value="${trip.trip_id}">${trip.trip_id} | ${trip.origin} ? ${trip.destination} (${trip.travel_date})</option>`).join('')
    : '<option value="">No trips available</option>';
}

function updateDashboardMetrics() {
  const today = formatDate(new Date());
  const todayTrips = tripsData.filter(trip => trip.travel_date === today);
  const currentMinutes = getCurrentTimeMinutes();
  const completedTrips = todayTrips.filter(trip => parseTime(trip.departure_time) < currentMinutes).length;
  const bookedSeats = ticketsData.filter(ticket => ticket.travel_date === today).length;
  const availableSeats = todayTrips.reduce((sum, trip) => sum + (Number(trip.available_seats) || 0), 0);
  const totalEarnings = ticketsData
    .filter(ticket => ticket.travel_date === today)
    .reduce((sum, ticket) => sum + (Number(ticket.fare) || 0), 0);

  dashboardFields.todaysTrips.textContent = todayTrips.length;
  dashboardFields.completedTrips.textContent = completedTrips;
  dashboardFields.bookedSeats.textContent = bookedSeats;
  dashboardFields.availableSeats.textContent = availableSeats;
  dashboardFields.earnings.textContent = `${totalEarnings} BDT`;
  dashboardFields.requestCount.textContent = requestsData.length;
}

let realtimeConnected = false;

function setConnected(connected) {
  realtimeConnected = !!connected;
  const el = document.getElementById('statusBanner');
  if (!el) return;
  if (!loggedInUser) {
    el.textContent = 'Not signed in';
    return;
  }
  el.textContent = realtimeConnected ? 'Realtime: connected' : 'Realtime: connecting...';
}

function startClock() {
  const clockEl = document.getElementById('clock');
  if (!clockEl) return;
  function tick() {
    try {
      const now = new Date();
      const dhaka = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Dhaka'
      }).format(now);
      clockEl.textContent = dhaka;
    } catch (e) {
      // fallback to local time
      const now = new Date();
      clockEl.textContent = now.toLocaleTimeString();
    }
  }
  tick();
  setInterval(tick, 1000);
}

function startRealtimeListeners() {
  const tripsQuery = query(tripsCollection, orderBy('travel_date'), orderBy('departure_time'));
  const ticketQuery = query(ticketsCollection, orderBy('created_at', 'desc'));
  const requestQuery = query(requestsCollection, where('requested_by', '==', loggedInUser), orderBy('created_at', 'desc'));

  let tripsReady = false, ticketsReady = false, requestsReady = false;

  onSnapshot(tripsQuery, snapshot => {
    tripsData = snapshot.docs.map(docSnap => ({ ...docSnap.data(), id: docSnap.id }));
    tripsReady = true;
    renderTripsTable();
    updateTripSelection();
    updateDashboardMetrics();
    if (tripsReady && ticketsReady && requestsReady) setConnected(true);
  });

  onSnapshot(ticketQuery, snapshot => {
    ticketsData = snapshot.docs.map(docSnap => ({ ...docSnap.data(), ticket_id: docSnap.id }));
    ticketsReady = true;
    renderTicketsTable();
    updateDashboardMetrics();
    if (tripsReady && ticketsReady && requestsReady) setConnected(true);
  });

  onSnapshot(requestQuery, snapshot => {
    requestsData = snapshot.docs.map(docSnap => ({ ...docSnap.data(), request_id: docSnap.id }));
    requestsReady = true;
    renderRequestsTable();
    updateDashboardMetrics();
    if (tripsReady && ticketsReady && requestsReady) setConnected(true);
  });

  setConnected(false);
}

function attachFormHandlers() {
  loginForm.addEventListener('submit', handleLogin);
  tripForm.addEventListener('submit', handleTripCreate);
  tripEditForm.addEventListener('submit', handleTripEdit);
  ticketForm.addEventListener('submit', handleTicketCreate);
  ticketEditForm.addEventListener('submit', handleTicketEdit);
  requestForm.addEventListener('submit', handleRequestCreate);
  requestEditForm.addEventListener('submit', handleRequestEdit);

  document.getElementById('tripEditCancel').addEventListener('click', () => tripEditSection.classList.add('hidden'));
  document.getElementById('ticketEditCancel').addEventListener('click', () => ticketEditSection.classList.add('hidden'));
  document.getElementById('requestEditCancel').addEventListener('click', () => requestEditSection.classList.add('hidden'));
}

showScreen('login');
bindButtons();
attachFormHandlers();
