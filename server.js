const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: "*" }
});

// Servir archivos estáticos (HTML)
app.use(express.static('public'));

io.on('connection', (socket) => {
  console.log('Nuevo dispositivo conectado:', socket.id);

  // Escuchar cuando el conductor envía sus coordenadas reales
  socket.on('ubicacion_conductor', (data) => {
    // data = { conductorId: 'C123', lat: 4.6097, lng: -74.0817 }
    console.log(`Ubicación recibida de ${data.conductorId}:`, data.lat, data.lng);

    // Retransmitir la ubicación en tiempo real a los clientes/administradores
    io.emit('posicion_actualizada', data);
  });

  socket.on('disconnect', () => {
    console.log('Dispositivo desconectado:', socket.id);
  });
});

server.listen(3000, () => {
  console.log('Servidor corriendo en http://localhost:3000');
});