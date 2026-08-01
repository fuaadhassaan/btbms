// firebase-init.js
// Exports initialized Firebase app and Firestore `db`.
import { initializeApp } from 'https://www.gstatic.com/firebasejs/12.17.0/firebase-app.js';
import { getFirestore } from 'https://www.gstatic.com/firebasejs/12.17.0/firebase-firestore.js';

const firebaseConfig = {
  apiKey: "AIzaSyDF2L2QCs9g0KGzyLGp03aQTaZ6A20zdPk",
  authDomain: "btbms-b79f0.firebaseapp.com",
  projectId: "btbms-b79f0",
  storageBucket: "btbms-b79f0.firebasestorage.app",
  messagingSenderId: "1070711151652",
  appId: "1:1070711151652:web:93bab4d9f4b49f42184cb1",
  measurementId: "G-718G445C6N"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

export { app, db };
