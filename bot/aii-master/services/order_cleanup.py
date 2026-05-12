import logging
from datetime import datetime

from config import ORDERS_FILE
from loader import scheduler
from services.db_compat import save_database, soft_delete_order
from storage import orders_db, save_data


logger = logging.getLogger(__name__)
ORDER_CLEANUP_JOB_ID = "orders_cleanup"


def cleanup_old_orders():
    today = datetime.now().strftime("%Y-%m-%d")
    deleted_count = 0

    for employee_id, employee_orders in list(orders_db.items()):
        actual_orders = []
        for order in employee_orders:
            order_date = order.get("date")
            if order_date and order_date < today:
                soft_delete_order(order)
                deleted_count += 1
                continue
            actual_orders.append(order)
        orders_db[employee_id] = actual_orders

    if deleted_count:
        save_data(ORDERS_FILE, orders_db)
        save_database()
        logger.info("Удалено старых заявок: %s", deleted_count)
    else:
        logger.info("Старых заявок для удаления нет")


def schedule_order_cleanup():
    scheduler.add_job(
        cleanup_old_orders,
        "cron",
        hour=10,
        minute=0,
        id=ORDER_CLEANUP_JOB_ID,
        replace_existing=True,
    )
    if not scheduler.running:
        try:
            scheduler.start()
        except RuntimeError:
            logger.warning("Планировщик очистки заявок будет запущен при старте event loop")
