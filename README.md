# BTBMS | Bus Ticket Booking and Management System
BTBMS – A complete Bus Ticket Booking &amp; Management System | Real-time seat booking, role-based dashboards (Admin, Counter Manager), and insightful analytics – all in one platform.

📌 Changelog
**Version 1.0 – Initial Release**

🎉 First stable release of BTBMS (Bus Ticket Booking & Management System)

**👤 Passenger's Ticket Booking:**

-Search buses by city & date

-Real-time seat availability

-Select up to 4 seats per booking

-Multiple payment options (Bkash, Card)

**🏢 Admin:**

-Manage trips CRUD (Create, Update, Delete, Overview)

-Manage tickets CRUD (Create, Update, Delete, Overview)

-Overview company stats (daily, monthly, yearly with charts)

-Handle requests from managers

**🎟️ Counter Manager:**

-View trip schedules

-Ticket management CRUD (create, update, delete, overview)

-Request to Admin

**🎨 UI & Styling:**

-Role-based dashboards with color based themes for better user reliability

-Separate HTML and CSS files for each module

**🔥 Firebase / Firestore Data:**

- Project: `btbms-b79f0`

- Firestore database: `(default)`

- Collections: `buses`, `trips`, `bookings`

- `buses` documents store bus metadata, including `bus_id` and `total_seats`

- `trips` documents store route, time, fare, travel date, and current `available_seats`

- `bookings` documents store `trip_id`, `seat_label`, passenger details, contact info, payment method, and timestamp

- In the Firebase Console, open **Build > Firestore Database > Data** to show these collections to your professor

- Use [migrate.html](migrate.html) only for a full reset/import; use the new **Seed New Trips Only** action to add the 06/08/2026 to 13/08/2026 schedule without touching booked seats

**Please note that this version doesn't represent the full version, more features and testing are under development.**

**All Rights Reserved by Md. Fuad Hasan, 2026© | For enquiries, email at: fuaadhassaan@gmail.com**

