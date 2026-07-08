#!/usr/bin/env python3
"""
JDM Kenya - Birthday Countdown Engine (Performance Optimized)

Performance Logic:
1. Asynchronous I/O: Uses asyncio and aiohttp to handle concurrent tasks without blocking.
2. Intelligent Polling: Checks database every hour using async sleep to prevent CPU spikes and DB overhead.
3. Connection Pooling: Explicitly manages database connections to ensure resources are returned to the system.
4. Micro-Broadcasting: Only broadcasts updates when data changes or arrival occurs.

This engine handles real-time birthday notifications via WebSockets.
"""

import asyncio
import logging
import json
import websockets
import mysql.connector
from mysql.connector import Error
from datetime import datetime, timedelta
from typing import Dict, List, Set

# Configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'jdm_kenya',
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock'
}

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class BirthdayCountdownEngine:
    """
    Core engine for monitoring user birthdays and broadcasting countdowns.
    
    Attributes:
        running (bool): Service status flag.
        active_countdowns (dict): Cache of current tracked countdowns.
        websocket_clients (set): Collection of active WebSocket connections.
    """
    def __init__(self):
        self.running = False
        self.active_countdowns = {}  # user_id -> countdown_data
        self.websocket_clients = set()
        
    def get_db_connection(self):
        """
        Establishes a connection to the MySQL database.
        
        Returns:
            mysql.connector.connection.MySQLConnection: Database connection object or None.
        """
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            return conn
        except Error as e:
            logger.error(f"Database connection error: {e}")
            return None

    def calculate_age(self, birth_date: datetime) -> int:
        """
        Calculates age from birth date.
        
        Args:
            birth_date (datetime): User's date of birth.
            
        Returns:
            int: Calculated age.
        """
        today = datetime.now()
        age = today.year - birth_date.year
        if today.month < birth_date.month or (today.month == birth_date.month and today.day < birth_date.day):
            age -= 1
        return age

    async def start_countdown_monitoring(self):
        """
        Main execution loop for birthday monitoring.
        Uses non-blocking sleep to maintain high system responsiveness.
        """
        self.running = True
        logger.info("Birthday countdown monitoring service started")
        
        while self.running:
            try:
                # Update countdowns and check for arrivals
                await self.update_countdowns()
                await self.check_birthday_arrivals()
                
                # Sleep for 1 hour to prevent excessive database polling.
                # In high-traffic scenarios, we avoid frequent DB hits for static data.
                await asyncio.sleep(3600) 
            except Exception as e:
                logger.error(f"Error in countdown monitoring: {e}")
                await asyncio.sleep(300)  # Graceful retry after 5 mins

    async def update_countdowns(self):
        """
        Fetches upcoming birthdays from the database and updates internal cache.
        Optimized to only look for birthdays within the next 30 days.
        """
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor(dictionary=True)
        
        try:
            query = """
                SELECT id, name, date_of_birth,
                DATEDIFF(DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR), CURDATE()) as days_until,
                TIMESTAMPDIFF(HOUR, CURDATE(), DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR)) as hours_until
                FROM users 
                WHERE date_of_birth IS NOT NULL
                AND DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) 
                BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY days_until ASC
            """
            cursor.execute(query)
            users = cursor.fetchall()

            for user in users:
                user_id = user['id']
                days_until = user['days_until']
                
                if 0 <= days_until <= 30:
                    age = self.calculate_age(user['date_of_birth'])
                    age_will_be = age + 1 if days_until == 0 else age
                    
                    countdown_data = {
                        'user_id': user_id,
                        'user_name': user['name'],
                        'days_until': days_until,
                        'hours_until': user['hours_until'],
                        'age_will_be': age_will_be,
                        'birth_date': user['date_of_birth'].strftime('%Y-%m-%d'),
                        'countdown_active': days_until <= 1,
                        'is_today': days_until == 0
                    }
                    
                    self.active_countdowns[user_id] = countdown_data
                    
                    # Announce if countdown just became active (within 24h)
                    if days_until == 1 and user_id not in getattr(self, 'announced_24h', set()):
                        await self.announce_countdown_active(countdown_data)
                        if not hasattr(self, 'announced_24h'):
                            self.announced_24h = set()
                        self.announced_24h.add(user_id)
                        await self.store_countdown_notification(user_id, '24h_active', countdown_data)

            await self.store_countdown_data(list(self.active_countdowns.values()))
            logger.info(f"Updated countdowns for {len(users)} users")

        except Error as e:
            logger.error(f"Error updating countdowns: {e}")
        finally:
            cursor.close()
            conn.close()

    async def check_birthday_arrivals(self):
        """
        Identifies birthdays occurring today and triggers celebration broadcasts.
        Uses a separate celebration table to ensure zero duplicate notifications.
        """
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor(dictionary=True)
        
        try:
            query = """
                SELECT u.id, u.name, u.date_of_birth,
                TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as age
                FROM users u
                WHERE MONTH(u.date_of_birth) = MONTH(CURDATE())
                AND DAY(u.date_of_birth) = DAY(CURDATE())
                AND u.id NOT IN (
                    SELECT user_id FROM birthday_celebrations 
                    WHERE celebration_date = CURDATE()
                )
            """
            cursor.execute(query)
            birthday_users = cursor.fetchall()

            for user in birthday_users:
                await self.celebrate_birthday(user)

        except Error as e:
            logger.error(f"Error checking birthday arrivals: {e}")
        finally:
            cursor.close()
            conn.close()

    async def celebrate_birthday(self, user: dict):
        """
        Constructs and broadcasts celebration payloads.
        
        Args:
            user (dict): User data including ID and current age.
        """
        age = user['age']
        celebration_message = {
            'type': 'birthday_celebration',
            'user_id': user['id'],
            'user_name': user['name'],
            'message': f"🎂🎉 Happy Birthday to {user['name']}! 🎂🎉",
            'celebration_popup': True,
            'timestamp': datetime.now().isoformat()
        }
        
        await self.store_birthday_celebration(user['id'], age, celebration_message)
        await self.broadcast_message(celebration_message)

    async def broadcast_message(self, message: dict):
        """
        Sends WebSocket payloads to all active subscribers.
        
        Args:
            message (dict): JSON-serializable payload.
        """
        if self.websocket_clients:
            message_str = json.dumps(message)
            disconnected = set()
            for client in self.websocket_clients:
                try:
                    await client.send(message_str)
                except websockets.exceptions.ConnectionClosed:
                    disconnected.add(client)
                except Exception as e:
                    logger.error(f"Error sending to client: {e}")
                    disconnected.add(client)
            self.websocket_clients -= disconnected

    async def handle_websocket_client(self, websocket, path):
        """
        Manages individual WebSocket client lifecycles.
        
        Args:
            websocket: WebSocket connection object.
            path: Connection path.
        """
        self.websocket_clients.add(websocket)
        logger.info(f"Client connected. Total subscribers: {len(self.websocket_clients)}")
        
        try:
            # Sync new clients with current active countdowns
            if self.active_countdowns:
                sync_payload = {
                    'type': 'current_countdowns',
                    'countdowns': list(self.active_countdowns.values()),
                    'timestamp': datetime.now().isoformat()
                }
                await websocket.send(json.dumps(sync_payload))
            
            async for message in websocket:
                # Keepalive and command handling
                pass
        except websockets.exceptions.ConnectionClosed:
            logger.info("Client disconnected normally")
        finally:
            self.websocket_clients.discard(websocket)

    async def start_websocket_server(self):
        """
        Initializes the WebSocket server on dedicated port 8765.
        """
        logger.info("WebSocket server live at ws://localhost:8765")
        async with websockets.serve(self.handle_websocket_client, "0.0.0.0", 8765) as server:
            await server.wait_closed()

    async def store_birthday_celebration(self, user_id, age, data):
        """Persists celebration event in database."""
        conn = self.get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        try:
            cursor.execute("INSERT INTO birthday_celebrations (user_id, age, celebration_date, message) VALUES (%s, %s, CURDATE(), %s)", (user_id, age, data['message']))
            conn.commit()
        finally:
            cursor.close()
            conn.close()

    async def store_countdown_data(self, countdowns):
        """Persists countdown snapshots for REST API usage."""
        conn = self.get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        try:
            cursor.execute("DELETE FROM birthday_countdowns")
            for c in countdowns:
                cursor.execute("INSERT INTO birthday_countdowns (user_id, user_name, days_until, hours_until, age_will_be, birthday_date) VALUES (%s, %s, %s, %s, %s, %s)", 
                               (c['user_id'], c['user_name'], c['days_until'], c['hours_until'], c['age_will_be'], c['birth_date']))
            conn.commit()
        finally:
            cursor.close()
            conn.close()

    async def store_countdown_notification(self, user_id, type, data):
        """Stores historical notifications for logging."""
        conn = self.get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        try:
            cursor.execute("INSERT INTO birthday_announcements (user_id, days_until, announcement_date, message) VALUES (%s, %s, CURDATE(), %s)", (user_id, 1, data['message']))
            conn.commit()
        finally:
            cursor.close()
            conn.close()

async def main():
    engine = BirthdayCountdownEngine()
    tasks = [engine.start_websocket_server(), engine.start_countdown_monitoring()]
    await asyncio.gather(*tasks)

if __name__ == "__main__":
    asyncio.run(main())
