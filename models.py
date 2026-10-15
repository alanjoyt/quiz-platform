from datetime import datetime, timezone
from flask_sqlalchemy import SQLAlchemy
from flask_login import UserMixin

db = SQLAlchemy()

class User(db.Model, UserMixin):
    id = db.Column(db.Integer, primary_key=True)
    email = db.Column(db.String(120), unique=True, nullable=False, index=True)
    username = db.Column(db.String(80), unique=True, nullable=False)
    password = db.Column(db.String(255), nullable=True)
    browser_fingerprint = db.Column(db.String(100), nullable=True)
    role = db.Column(db.String(20), default='user')  # 'user' or 'admin'
    is_verified = db.Column(db.Boolean, default=False)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    @property
    def total_score(self):
        # Calculate total score from user responses
        return sum(res.points_earned + res.speed_bonus for res in self.responses)

    @property
    def base_score(self):
        return sum(res.points_earned for res in self.responses)

    @property
    def speed_bonus_score(self):
        return sum(res.speed_bonus for res in self.responses)

class QuizConfig(db.Model):
    key = db.Column(db.String(50), primary_key=True)
    value = db.Column(db.String(255), nullable=False)

    @classmethod
    def get_val(cls, key, default=None):
        cfg = cls.query.filter_by(key=key).first()
        return cfg.value if cfg else default

    @classmethod
    def set_val(cls, key, val):
        cfg = cls.query.filter_by(key=key).first()
        if not cfg:
            cfg = cls(key=key)
            db.session.add(cfg)
        cfg.value = str(val)
        db.session.commit()

class Question(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    text = db.Column(db.Text, nullable=False)
    option_a = db.Column(db.String(255), nullable=False)
    option_b = db.Column(db.String(255), nullable=False)
    option_c = db.Column(db.String(255), nullable=False)
    option_d = db.Column(db.String(255), nullable=False)
    correct_option = db.Column(db.String(1), nullable=False)  # 'A', 'B', 'C', 'D'
    points = db.Column(db.Integer, default=10)

class UserQuizState(db.Model):
    user_id = db.Column(db.Integer, db.ForeignKey('user.id'), primary_key=True)
    current_question_id = db.Column(db.Integer, db.ForeignKey('question.id'), nullable=True)
    current_question_started_at = db.Column(db.DateTime, nullable=True)
    quiz_started_at = db.Column(db.DateTime, nullable=True)
    quiz_completed_at = db.Column(db.DateTime, nullable=True)
    is_disqualified = db.Column(db.Boolean, default=False)
    disqualification_reason = db.Column(db.String(255), nullable=True)
    warnings_count = db.Column(db.Integer, default=0)

    user = db.relationship('User', backref=db.backref('quiz_state', uselist=False))
    current_question = db.relationship('Question')

class UserResponse(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('user.id'), nullable=False)
    question_id = db.Column(db.Integer, db.ForeignKey('question.id'), nullable=False)
    selected_option = db.Column(db.String(1), nullable=True)  # 'A', 'B', 'C', 'D' or None (timeout)
    is_correct = db.Column(db.Boolean, default=False)
    points_earned = db.Column(db.Integer, default=0)
    speed_bonus = db.Column(db.Integer, default=0)
    response_time_ms = db.Column(db.Integer, default=0)
    submitted_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    user = db.relationship('User', backref=db.backref('responses', lazy=True))
    question = db.relationship('Question')
