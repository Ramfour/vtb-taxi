from aiogram.fsm.state import State, StatesGroup


class AuthState(StatesGroup):
    waiting_for_id = State()
    waiting_for_address = State()
    waiting_for_address_confirm = State()
    waiting_for_phone = State()


class ReminderState(StatesGroup):
    waiting_for_time = State()
    waiting_for_schedule = State()
    waiting_for_custom_days = State()


class OrderState(StatesGroup):
    waiting_for_choice = State()
    waiting_for_time = State()
    waiting_for_address = State()
    waiting_for_address_confirm = State()
    waiting_for_phone = State()
    waiting_for_confirmation = State()


class ManagerState(StatesGroup):
    waiting_for_reject_reason = State()
    waiting_for_edit_data = State()
