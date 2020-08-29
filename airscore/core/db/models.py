from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm.exc import MultipleResultsFound
from .conn import db_session, Session
Base = declarative_base()
metadata = Base.metadata

# db = Session()


class BaseModel(Base):
    __abstract__ = True

    def as_dict(self) -> dict:
        return dict({c.key: getattr(self, c.key) for c in self.__table__.columns})

    @classmethod
    def get_by_id(cls, value: int):
        with db_session() as db:
            print(f'get_by_id session id: {id(db)}')
            return db.query(cls).get(int(value))

    @classmethod
    def get_all(cls, **kvargs):
        with db_session() as db:
            print(f'get_all session id: {id(db)}')
            return db.query(cls).filter_by(**kvargs).all()

    @classmethod
    def get_one(cls, **kvargs):
        with db_session() as db:
            print(f'get_one session id: {id(db)}')
            try:
                return db.query(cls).filter_by(**kvargs).one_or_none()
            except MultipleResultsFound:
                print(f"Error: Multiple results found")
                return None

    def from_obj(self, obj):
        """ populate a Table row object from an object
            Input:
                obj  - OBJ: object"""
        try:
            for x in self.__table__.columns.keys():
                if hasattr(obj, x):
                    setattr(self, x, getattr(obj, x))
            return self
        except TypeError as e:
            print(f'Error populating table row: obj is not iterable ({e})')

    def populate(self, obj: object) -> object:
        """ Associate query result with class object attributes, using same name
            Input:
                obj     - OBJ: object with attributes to populate with query result
                result  - OBJ: query result (should be one row)"""
        '''check if result has one row'''
        row = self[0] if isinstance(self, list) else self
        for x in obj.__dict__.keys():
            if hasattr(row, x):
                setattr(obj, x, getattr(row, x))
        return obj

    def before_save(self, *args, **kwargs):
        pass

    def after_save(self, *args, **kwargs):
        pass

    def save(self):
        self.before_save()
        with db_session() as db:
            print(f'save session id: {id(db)}')
            db.add(self)
        self.after_save()

    def before_update(self, *args, **kwargs):
        pass

    def after_update(self, *args, **kwargs):
        pass

    def update(self, *args, **kwargs):
        self.before_update(*args, **kwargs)
        with db_session() as db:
            print(f'update session id: {id(db)}')
            for key, value in kwargs.items():
                if key in self.__table__.columns.keys():
                    setattr(self, key, value)
            db.commit()
        self.after_update(*args, **kwargs)

    def delete(self, commit=True):
        with db_session() as db:
            print(f'delete session id: {id(db)}')
            db.delete(self)

    @classmethod
    def before_bulk_create(cls, iterable, *args, **kwargs):
        pass

    @classmethod
    def after_bulk_create(cls, model_objs, *args, **kwargs):
        pass

    @classmethod
    def bulk_create(cls, iterable, *args, **kwargs):
        cls.before_bulk_create(iterable, *args, **kwargs)
        model_objs = []
        for data in iterable:
            if not isinstance(data, cls):
                data = cls(**data)
            model_objs.append(data)
        with db_session() as db:
            print(f'bulk_save session id: {id(db)}')
            db.bulk_save_objects(model_objs)
        cls.after_bulk_create(model_objs, *args, **kwargs)
        return model_objs

    @classmethod
    def bulk_create_or_none(cls, iterable, *args, **kwargs):
        try:
            return cls.bulk_create(iterable, *args, **kwargs)
        except:
            return None

    def save_or_update(self):
        try:
            key = next((c.name for c in self.__table__.columns.values() if c.primary_key), None)
            idv = getattr(self, key)
            if idv and self.get_by_id(idv):
                self.update()
            else:
                self.save()
        except:
            return None
