<?php
namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\base\Security;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\IdentityInterface;

use common\models\Role;
use common\models\Status;
use common\models\UserType;
use common\models\Profile;

/**
 * This is the model class for table "p2m_user".
 *
 * @property integer $id
 * @property string $username
 * @property string $auth_key
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $email
 * @property integer $role_id
 * @property integer $status_id
 * @property integer $user_type_id
 * @property integer $created_at
 * @property integer $updated_at
 *
	 * @property Profile[] $profiles
	 * @property Role $role
	 * @property Status $status
	 * @property UserType $userType
 */
class User extends \common\models\base\UserBase implements IdentityInterface
{
	 const STATUS_ACTIVE = 1;

	/**
	 * behaviors
	 */
	public function behaviors()
	{
		return [
			//BlameableBehavior::className(),
			TimestampBehavior::className(),
		];
	}

	/**
	 * validation rules
	 */
	public function rules()
	{
		return [
			['status_id', 'default', 'value' => self::STATUS_ACTIVE],
			[['status_id'],'in', 'range'=>array_keys($this->getStatusList())],

			['role_id', 'default', 'value' => 1],
			[['role_id'],'in', 'range'=>array_keys($this->getRoleList())],

			['user_type_id', 'default', 'value' => 1],
			[['user_type_id'],'in', 'range'=>array_keys($this->getUserTypeList())],

			['username', 'filter', 'filter' => 'trim'],
			['username', 'required'],
			['username', 'unique'],
			['username', 'string', 'min' => 2, 'max' => 255],

			['email', 'filter', 'filter' => 'trim'],
			['email', 'required'],
			['email', 'email'],
			['email', 'unique'],
		];
	}

	/* Your model attribute labels */

	public function attributeLabels()
	{
		return [
			'id' => 'ID',
			'username' => 'Username',
			'auth_key' => 'Auth Key',
			'password_hash' => 'Password',
			'password_reset_token' => 'Password Reset Token',
			'email' => 'Email',
			'role_id' => 'Role',
			'status_id' => 'Status',
			'user_type_id' => 'User Type',
			'created_at' => 'Created At',
			'updated_at' => 'Updated At',

			'roleName' => Yii::t('app', 'Role'),
			'statusName' => Yii::t('app', 'Status'),
			'profileId' => Yii::t('app', 'Profile'),
			'profileLink' => Yii::t('app', 'Profile'),
			'userLink' => Yii::t('app', 'User'),
			'username' => Yii::t('app', 'User'),
			'userTypeName' => Yii::t('app', 'User Type'),
			'userTypeId' => Yii::t('app', 'User Type'),
			'userIdLink' => Yii::t('app', 'ID'),
		];
	}

	/**
	 * @findIdentity
	 */
	public static function findIdentity($id)
	{
		return static::findOne(['id' => $id, 'status_id' => self::STATUS_ACTIVE]);
	}

	/**
	 * @inheritdoc
	 */
	public static function findIdentityByAccessToken($token, $type = null)
	{
		throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
	}

	/**
	 * Finds user by username
	 * broken into 2 lines to avoid wordwrapping * @param string $username
	 * @return static|null
	 */
	public static function findByUsername($username)
	{
		return static::findOne(['username' => $username, 'status_id' => self::STATUS_ACTIVE]);
	}

	/**
	 * Finds user by password reset token
	 *
	 * @param string $token password reset token
	 * @return static|null
	 */
	public static function findByPasswordResetToken($token)
	{
		if (!static::isPasswordResetTokenValid($token)) {
			return null;
		}

		return static::findOne([
			'password_reset_token' => $token,
			'status_id' => self::STATUS_ACTIVE,
		]);
	}

	/**
	 * Finds out if password reset token is valid
	 *
	 * @param string $token password reset token
	 * @return boolean
	 */
	public static function isPasswordResetTokenValid($token)
	{
		if (empty($token)) {
			return false;
		}
		$expire = Yii::$app->params['user.passwordResetTokenExpire'];
		$parts = explode('_', $token);
		$timestamp = (int) end($parts);
		return $timestamp + $expire >= time();
	}

	/**
	 * @getId
	 */
	public function getId()
	{
		return $this->getPrimaryKey();
	}

	/**
	 * @getAuthKey
	 */
	public function getAuthKey()
	{
		return $this->auth_key;
	}

	/**
	 * @validateAuthKey
	 */
	public function validateAuthKey($authKey)
	{
		return $this->getAuthKey() === $authKey;
	}

	/**
	 * Validates password
	 *
	 * @param string $password password to validate
	 * @return boolean if password provided is valid for current user
	 */
	public function validatePassword($password)
	{
		return Yii::$app->security->validatePassword($password, $this->password_hash);
	}

	/**
	 * Generates password hash from password and sets it to the model
	 *
	 * @param string $password
	 */
	public function setPassword($password)
	{
		 $this->password_hash = Yii::$app->security->generatePasswordHash($password);
	}

	/**
	 * Generates "remember me" authentication key
	 */
	public function generateAuthKey()
	{
		$this->auth_key = Yii::$app->security->generateRandomString();
	}

	/**
	 * Generates new password reset token
	 */
	public function generatePasswordResetToken()
	{
		$this->password_reset_token = Yii::$app->security->generateRandomString()  . '_' . time();
	}

	/**
	 * Removes password reset token
	 */
	public function removePasswordResetToken()
	{
		$this->password_reset_token = null;
	}

	//*** relationships ***//

	/**
	 * get role relationship
	 */
	public function getRole()
	{
		return $this->hasOne(Role::className(), ['id' => 'role_id']);
	}

	/**
	 * get role name
	 */
	public function getRoleName()
	{
		return $this->role ? $this->role->role_name : '- no role -';
	}

	/**
	 * get list of roles for dropdown
	 */
	public static function getRoleList()
	{
		$droptions = Role::find()->asArray()->all();
		return ArrayHelper::map($droptions, 'id', 'role_name');
	}

	/**
	 * get status relationship
	 */
	public function getStatus()
	{
		return $this->hasOne(Status::className(), ['id' => 'status_id']);
	}

	/**
	 * get status name
	 */
	public function getStatusName()
	{
		return $this->status ? $this->status->status_name : '- no status -';
	}

	/**
	 * get list of statuses for dropdown
	 */
	public static function getStatusList()
	{
		$droptions = Status::find()->asArray()->all();
		return ArrayHelper::map($droptions, 'id', 'status_name');
	}

	/**
	 * get user type relationship
	 */
	public function getUserType()
	{
		return $this->hasOne(UserType::className(), ['id' => 'user_type_id']);
	}

	/**
	 * get user type name
	 */
	public function getUserTypeName()
	{
		return $this->userType ? $this->userType->user_type_name : '- no user type -';
	}

	/**
	 * get list of user types for dropdown
	 */
	public static function getUserTypeList()
	{
		$droptions = UserType::find()->asArray()->all();
		return ArrayHelper::map($droptions, 'id', 'user_type_name');
	}

	/**
	 * get user type id
	 */
	public function getUserTypeId()
	{
		return $this->userType ? $this->userType->id : 'none';
	}

	/**
	 * get profile relationship
	 */
	public function getProfile()
	{
		return $this->hasOne(Profile::className(), ['user_id' => 'id']);
	}

	/**
	 * @getProfileId
	 */
	public function getProfileId()
	{
		return $this->profile ? $this->profile->id : 'none';
	}

	/**
	 * @getProfileLink
	 */
	public function getProfileLink()
	{
		$url = Url::to(['profile/view', 'id'=>$this->profileId]);
		return Html::a($this->profile ? 'profile' : 'none', $url, []);
	}

	/**
	 * get user id Link
	 */
	public function getUserIdLink()
	{
		$url = Url::to(['user/update', 'id'=>$this->id]);
		return Html::a($this->id, $url, []);
	}

	/**
	 * @getUserLink
	 */
	public function getUserLink()
	{
		$url = Url::to(['user/view', 'id'=>$this->id]);
		return Html::a($this->username, $url, []);
	}

	/**
	 * Finds user by email - addition for email based login
	 *
	 * @param string $email
	 * @return static|null
	 */
	public static function findByEmail($email)
	{
		return static::findOne([
			'email' => $email,
			'status_id' => self::STATUS_ACTIVE,
		]);
	}
}
