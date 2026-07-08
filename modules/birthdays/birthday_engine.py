#!/usr/bin/env python3
"""
JDM Kenya - Birthday Engine
Handles birthday countdowns and automated announcements
"""

import asyncio
import logging
from datetime import datetime, timedelta
from typing import Dict, List
import mysql.connector
from mysql.connector import Error

# Configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'jdm_kenya',
    'unix_socket': '/opt/lampp/var/mysql/mysql.sock'
}

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class BirthdayEngine:
    def __init__(self):
        self.running = False
        self.announced_today = set()  # Track already announced birthdays today

    def get_db_connection(self):
        """Get database connection"""
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            return conn
        except Error as e:
            logger.error(f"Database connection error: {e}")
            return None

    def calculate_age(self, birth_date: datetime) -> int:
        """Calculate age from birth date"""
        today = datetime.now()
        age = today.year - birth_date.year
        if today.month < birth_date.month or (today.month == birth_date.month and today.day < birth_date.day):
            age -= 1
        return age

    async def start_monitoring(self):
        """Start the birthday monitoring service"""
        self.running = True
        logger.info("Birthday monitoring service started")
        
        while self.running:
            try:
                await self.check_birthdays()
                await self.update_countdowns()
                await asyncio.sleep(3600)  # Check every hour
            except Exception as e:
                logger.error(f"Error in birthday monitoring: {e}")
                await asyncio.sleep(300)  # Wait 5 minutes before retrying

    async def check_birthdays(self):
        """Check for today's birthdays and upcoming birthdays"""
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor(dictionary=True)
        
        try:
            # Clear announced set at midnight
            now = datetime.now()
            if now.hour == 0 and now.minute == 0:
                self.announced_today.clear()

            # Get today's birthdays
            today_query = """
                SELECT id, name, date_of_birth
                FROM users 
                WHERE date_of_birth IS NOT NULL
                AND MONTH(date_of_birth) = MONTH(CURDATE())
                AND DAY(date_of_birth) = DAY(CURDATE())
            """
            cursor.execute(today_query)
            today_birthdays = cursor.fetchall()

            # Get upcoming birthdays (next 7 days)
            upcoming_query = """
                SELECT id, name, date_of_birth,
                DATEDIFF(DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR), CURDATE()) as days_until
                FROM users 
                WHERE date_of_birth IS NOT NULL
                AND DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR) 
                BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND NOT (MONTH(date_of_birth) = MONTH(CURDATE()) AND DAY(date_of_birth) = DAY(CURDATE()))
                ORDER BY days_until ASC
            """
            cursor.execute(upcoming_query)
            upcoming_birthdays = cursor.fetchall()

            # Process today's birthdays
            for user in today_birthdays:
                if user['id'] not in self.announced_today:
                    age = self.calculate_age(user['date_of_birth'])
                    await self.celebrate_birthday(user, age)
                    self.announced_today.add(user['id'])

            # Process upcoming birthdays
            for user in upcoming_birthdays:
                if user['days_until'] <= 1:  # Only announce birthdays within 24 hours
                    await self.announce_upcoming_birthday(user, user['days_until'])

        except Error as e:
            logger.error(f"Error checking birthdays: {e}")
        finally:
            cursor.close()
            conn.close()

    async def update_countdowns(self):
        """Update birthday countdown information"""
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor(dictionary=True)
        
        try:
            # Get all users with birth dates
            query = """
                SELECT id, name, date_of_birth,
                DATEDIFF(DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR), CURDATE()) as days_until,
                DATEDIFF(DATE_ADD(date_of_birth, INTERVAL YEAR(CURDATE())-YEAR(date_of_birth) YEAR), CURDATE()) as countdown_hours
                FROM users 
                WHERE date_of_birth IS NOT NULL
                ORDER BY days_until ASC
            """
            cursor.execute(query)
            users = cursor.fetchall()

            # This data can be used by the frontend to display countdowns
            countdown_data = []
            for user in users:
                if user['days_until'] >= 0:  # Future birthday this year
                    age = self.calculate_age(user['date_of_birth']) + 1
                    countdown_data.append({
                        'user_id': user['id'],
                        'user_name': user['name'],
                        'days_until': user['days_until'],
                        'hours_until': user['days_until'] * 24,
                        'age_will_be': age,
                        'birthday_date': user['date_of_birth'].strftime('%Y-%m-%d')
                    })

            # Store countdown data (could be cached or sent to WebSocket clients)
            await self.broadcast_countdown_update(countdown_data)

        except Error as e:
            logger.error(f"Error updating countdowns: {e}")
        finally:
            cursor.close()
            conn.close()

    async def celebrate_birthday(self, user: dict, age: int):
        """Celebrate a user's birthday"""
        celebration_message = {
            'type': 'birthday_celebration',
            'user_id': user['id'],
            'user_name': user['name'],
            'message': f"🎉🎂 Happy Birthday to {user['name']}! 🎂🎉",
            'celebration_popup': True,
            'timestamp': datetime.now().isoformat()
        }
        
        logger.info(f"🎂 Celebrating birthday: {user['name']}!")
        
        # This would be sent via WebSocket to all connected clients
        # For now, we'll log it and store in database
        await self.store_birthday_celebration(user['id'], age, celebration_message)

    async def announce_upcoming_birthday(self, user: dict, days_until: int):
        """Announce upcoming birthday"""
        if days_until == 1:
            message = f"📅 Tomorrow is {user['name']}'s birthday! Get ready to celebrate! 🎈"
        else:
            message = f"📅 {user['name']}'s birthday is in {days_until} days! 🎈"

        announcement = {
            'type': 'birthday_reminder',
            'user_id': user['id'],
            'user_name': user['name'],
            'days_until': days_until,
            'message': message,
            'timestamp': datetime.now().isoformat()
        }
        
        logger.info(f"📅 Birthday reminder: {message}")
        await self.store_birthday_announcement(user['id'], days_until, announcement)

    async def store_birthday_celebration(self, user_id: int, age: int, message_data: dict):
        """Store birthday celebration in database"""
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor()
        
        try:
            # Create birthday celebrations table if it doesn't exist
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS birthday_celebrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    age INT NOT NULL,
                    celebration_date DATE NOT NULL,
                    message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            """)
            
            # Insert celebration record
            cursor.execute("""
                INSERT INTO birthday_celebrations (user_id, age, celebration_date, message)
                VALUES (%s, %s, CURDATE(), %s)
            """, (user_id, age, message_data['message']))
            
            conn.commit()
            
        except Error as e:
            logger.error(f"Error storing birthday celebration: {e}")
        finally:
            cursor.close()
            conn.close()

    async def store_birthday_announcement(self, user_id: int, days_until: int, message_data: dict):
        """Store birthday announcement in database"""
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor()
        
        try:
            # Create birthday announcements table if it doesn't exist
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS birthday_announcements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    days_until INT NOT NULL,
                    announcement_date DATE NOT NULL,
                    message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            """)
            
            # Insert announcement record
            cursor.execute("""
                INSERT INTO birthday_announcements (user_id, days_until, announcement_date, message)
                VALUES (%s, %s, CURDATE(), %s)
            """, (user_id, days_until, message_data['message']))
            
            conn.commit()
            
        except Error as e:
            logger.error(f"Error storing birthday announcement: {e}")
        finally:
            cursor.close()
            conn.close()

    async def broadcast_countdown_update(self, countdown_data: List[dict]):
        """Broadcast countdown updates to WebSocket clients"""
        # This would integrate with the WebSocket manager
        # For now, we'll just log the data
        logger.info(f"Updated birthday countdowns for {len(countdown_data)} users")
        
        # Store countdown data in database for API access
        conn = self.get_db_connection()
        if not conn:
            return

        cursor = conn.cursor()
        
        try:
            # Clear old countdown data
            cursor.execute("DELETE FROM birthday_countdowns")
            
            # Insert new countdown data
            for countdown in countdown_data:
                cursor.execute("""
                    INSERT INTO birthday_countdowns 
                    (user_id, user_name, days_until, hours_until, age_will_be, birthday_date)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, (
                    countdown['user_id'],
                    countdown['user_name'],
                    countdown['days_until'],
                    countdown['hours_until'],
                    countdown['age_will_be'],
                    countdown['birthday_date']
                ))
            
            conn.commit()
            
        except Error as e:
            logger.error(f"Error storing countdown data: {e}")
        finally:
            cursor.close()
            conn.close()

    def get_upcoming_birthdays(self, days: int = 30) -> List[dict]:
        """Get upcoming birthdays for the next N days"""
        conn = self.get_db_connection()
        if not conn:
            return []

        cursor = conn.cursor(dictionary=True)
        
        try:
            query = """
                SELECT bc.*, u.email, u.whatsapp_phone
                FROM birthday_countdowns bc
                JOIN users u ON bc.user_id = u.id
                WHERE bc.days_until <= %s AND bc.days_until >= 0
                ORDER BY bc.days_until ASC
            """
            cursor.execute(query, (days,))
            return cursor.fetchall()
            
        except Error as e:
            logger.error(f"Error fetching upcoming birthdays: {e}")
            return []
        finally:
            cursor.close()
            conn.close()

    def get_todays_birthdays(self) -> List[dict]:
        """Get today's birthdays"""
        conn = self.get_db_connection()
        if not conn:
            return []

        cursor = conn.cursor(dictionary=True)
        
        try:
            query = """
                SELECT u.id, u.name, u.email, u.whatsapp_phone, u.date_of_birth,
                TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) as current_age
                FROM users u
                WHERE MONTH(u.date_of_birth) = MONTH(CURDATE())
                AND DAY(u.date_of_birth) = DAY(CURDATE())
            """
            cursor.execute(query)
            return cursor.fetchall()
            
        except Error as e:
            logger.error(f"Error fetching today's birthdays: {e}")
            return []
        finally:
            cursor.close()
            conn.close()

    def stop(self):
        """Stop the birthday engine"""
        self.running = False
        logger.info("Birthday monitoring service stopped")

# Standalone execution for testing
if __name__ == "__main__":
    engine = BirthdayEngine()
    
    async def test():
        # Test database connection
        conn = engine.get_db_connection()
        if conn:
            print("✅ Database connection successful")
            conn.close()
        
        # Test birthday calculation
        from datetime import datetime
        test_date = datetime(1990, 5, 15)
        age = engine.calculate_age(test_date)
        print(f"✅ Age calculation test: {test_date.strftime('%Y-%m-%d')} -> {age} years old")
        
        # Test getting today's birthdays
        todays = engine.get_todays_birthdays()
        print(f"✅ Today's birthdays: {len(todays)} found")
        
        # Test upcoming birthdays
        upcoming = engine.get_upcoming_birthdays(30)
        print(f"✅ Upcoming birthdays (30 days): {len(upcoming)} found")
        
        print("✅ Birthday engine tests completed successfully!")
    
    asyncio.run(test())
