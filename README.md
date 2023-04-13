# Model

[jenssegers/model]をベースにしています

## Features

- アクセサーとミューテーター
- モデルから配列および JSON への変換
- 配列/JSON 変換の隠し属性
- 保護された入力可能な属性
- 配列/JSON 変換へのアクセサーとミューテーターの追加
- 属性キャスト
- カスタムキャスト
- 状態管理
- モデルアクション

## Installation

プライベートリポジトリなので下記を`composer.json`に追加

``` json
"repositories": [
    { "type": "vcs", "url": "git@github.com:atamiso/model.git" }
],
```

``` bash
composer require atamso/model
```

## Usage

基本的な利用方法はLaravelのModelと同じです

``` php
use Atamso\Model\Model ;

class Profile extends Model
{
	protected $fillable = [
		'name', 'location','disabled'
	];

	protected $casts = [
		'location' => Location::class ,
		'disabled' => 'boolean' ,
	];

	protected function serializeDate(DateTimeInterface $date)
	{
		return $date->format('Y-m-d H:i:s');
	}
}
```

## モデルアクション

例に出てくる`$client`は他サービスのオブジェクトなので実際はメソッド経由で行う

### Update

```php
protected function performUpdate()
{
    // updated_at更新を行う場合この部分に書く

    $dirty = $this->getDirty();

    if ( count( $dirty ) > 0 ) {
        $model = $this->update( $dirty ) ;

        // 値の更新を行ったModelを使って必要な処理を行う
        $client->update( $this->getKey() , $model->toArray() );
        
        // 更新情報書き込み
        $this->syncChanges();
    }

    return true;
}
```

### Insert

```php
protected function performInsert()
{
    // created_at更新を行う場合この部分に書く

    $attributes = $this->getAttributesForInsert();

    if ( $this->getIncrementing() ) {
        // ID発番を他のサービスで行う際の処理
        $this->insertAndSetId( $attributes );
    } else {
        if ( empty( $attributes ) ) {
            return true;
        }
        // ID発番を行わない場合の処理
        $client->create( $attributes ) ;
    }

    $this->exists = true;
    // wasRecentlyCreated InsertされたModelと更新されたModelの区別用フラグ
    $this->wasRecentlyCreated = true;

    return true;
}

protected function insertAndSetId( $attributes )
{
    $keyName = $this->getKeyName();
    
    $id = $client->getUniqueId() ; // ID発番処理を行う

    $this->setAttribute( $keyName, $id );
}
```

### Delete

```php
protected function performDeleteOnModel()
{
    $client->delete($this->getKey());
    $this->exists = false;
}
```

## Testing

``` bash
composer test
```

[jenssegers/model]:https://github.com/jenssegers/model
