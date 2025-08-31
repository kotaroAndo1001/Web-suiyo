# 前期最終課題 掲示板アプリ

### 主な機能
- テキスト投稿・画像投稿（MySQL保存）
- 自動でIDと日時を付与
- ページング機能（1ページ10件）
- レスアンカー機能（`>>番号` でリンク）
- 画像を5MB以下に自動縮小（ブラウザ側で実装）
- スマホ対応の青テーマデザイン（CSS）

---

## 必要環境
- Amazon Linux 2 (EC2など)
- Docker
- Docker Compose
- Git

---

## インストール方法（環境準備）

### Docker インストール & 自動起動化 (Amazon Linux2)
```bash
sudo yum install -y docker
sudo systemctl start dockers
sudo systemctl enable docker
sudo usermod -a -G docker ec2-user

```

## Docker Compose インストール
```bash
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-$(uname -s)-$(uname -m) \
 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

```

### Git インストール
```bash
sudo yum install -y git
```

### セットアップ手順
1. リポジトリを取得
```bash
git clone https://github.com/<ユーザー名>/<リポジトリ名>.git
cd <リポジトリ名>
```

2. コンテナをビルド・起動
```bash
docker compose build
docker compose up -d
```

3. MySQL にテーブルを作成
```sql
CREATE DATABASE kadai_db;
USE kadai_db;
CREATE TABLE bbs_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  body TEXT NOT NULL,
  image_filename VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
